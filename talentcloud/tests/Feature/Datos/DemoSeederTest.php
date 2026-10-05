<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Models\Actividad;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;

/*
 * El seeder de demostración deja la app con las personas y vacantes del
 * prototipo, coherentes con un proceso real (nada en el software que no
 * pudiera ocurrir en la realidad), y respeta el aislamiento entre empresas.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->marta = User::where('email', 'marta@nimbus.com')->firstOrFail();
});

it('crea las cuentas de demostración con su rol', function () {
    expect(User::where('email', 'admin@talentcloud.ai')->first()->rol())->toBe(Rol::Admin)
        ->and($this->marta->rol())->toBe(Rol::Reclutador)
        ->and($this->marta->empresa->nombre)->toBe('Nimbus Corp')
        ->and(User::where('email', 'marco@correo.com')->first()->candidato->nombre)->toBe('Marco Díaz')
        ->and(Empresa::where('nombre', 'Orbita Labs')->first()->estado)->toBe(EstadoEmpresa::Suspendida);
});

it('deja un Kanban de Desarrollador Backend coherente con el proceso', function () {
    $kanban = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Postulacion::query()
        ->with('candidato')
        ->whereHas('vacante', fn ($q) => $q->where('titulo', 'Desarrollador Backend'))
        ->orderBy('orden')
        ->get()
        ->groupBy(fn (Postulacion $p) => $p->etapa->value)
        ->map(fn ($columna) => $columna->pluck('candidato.nombre')->all())
        ->all());

    expect($kanban)->toEqual([
        EtapaPostulacion::Postulado->value => ['Marco Díaz'],
        EtapaPostulacion::Entrevista->value => ['Elena Ruiz'],
        EtapaPostulacion::Oferta->value => ['Sofía Nieto'],
        EtapaPostulacion::Descartado->value => ['Pablo Vega'],
    ]);
});

it('no tiene incoherencias entre etapas, entrevistas, evaluaciones y fechas', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach (Postulacion::with(['entrevistas', 'evaluaciones', 'vacante'])->get() as $postulacion) {
            $realizadas = $postulacion->entrevistas->where('estado', EstadoEntrevista::Completada);
            $activas = $postulacion->entrevistas->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada]);
            $quien = $postulacion->candidato_id.' en '.$postulacion->vacante->titulo;

            // Con una entrevista realizada o por hacer, nunca en una etapa anterior a "Entrevista".
            if ($realizadas->isNotEmpty() || $activas->isNotEmpty()) {
                expect($postulacion->etapa->antesDeEntrevista())->toBeFalse("Etapa anterior a la entrevista: {$quien}");
            }
            // Oferta o evaluación solo después de una entrevista realizada.
            if ($postulacion->etapa === EtapaPostulacion::Oferta || $postulacion->evaluaciones->isNotEmpty()) {
                expect($realizadas)->not->toBeEmpty("Oferta o evaluación sin entrevista: {$quien}");
            }
            // Lo pasado está realizado o cancelado; lo pendiente o confirmado es futuro.
            $postulacion->entrevistas->each(fn (Entrevista $e) => expect(
                in_array($e->estado, [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada], true) ? $e->inicia_at->isFuture() : $e->inicia_at->isPast()
            )->toBeTrue("Fecha de entrevista incoherente: {$quien}"));
            // Nadie postula a un borrador ni antes de que exista la vacante.
            expect($postulacion->vacante->estado)->not->toBe(EstadoVacante::Borrador)
                ->and($postulacion->created_at->greaterThanOrEqualTo($postulacion->vacante->created_at))->toBeTrue("Postuló antes de que existiera la vacante: {$quien}");
        }
    });
});

it('calcula el matching de la demo con el motor a partir de los CV', function () {
    $match = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Postulacion::query()
        ->with('candidato')
        ->whereHas('vacante', fn ($q) => $q->where('titulo', 'Desarrollador Backend'))
        ->get()
        ->mapWithKeys(fn (Postulacion $p) => [$p->candidato->nombre => $p->matchPorcentaje()]));

    // Los porcentajes son los del motor, no números fijos: se comprueba su coherencia.
    expect($match['Elena Ruiz'])->toBeGreaterThan($match['Marco Díaz'])
        ->and($match['Sofía Nieto'])->toBeGreaterThan($match['Marco Díaz'])
        ->and($match['Pablo Vega'])->toBeNull(); // CV escaneado sin texto
});

it('muestra a Marta solo las vacantes, entrevistas y evaluaciones de Nimbus Corp', function () {
    app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, function () {
        expect(Vacante::orderBy('titulo')->pluck('titulo')->all())->toBe(['Data Analyst', 'Desarrollador Backend'])
            ->and(Entrevista::count())->toBe(5)
            ->and(Evaluacion::orderByDesc('puntaje')->pluck('puntaje')->map(fn ($p) => (string) $p)->all())->toBe(['8.9', '8.6', '7.9']);
    });
});

it('muestra al admin SaaS las cinco vacantes de la plataforma', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => expect(Vacante::count())->toBe(5));
});

it('se puede ejecutar dos veces sin duplicar datos', function () {
    $this->seed(DatabaseSeeder::class);

    app(TenantContext::class)->ejecutarGlobal(function () {
        expect(User::count())->toBe(8)
            ->and(Vacante::count())->toBe(5)
            ->and(Postulacion::count())->toBe(7);
    });
    expect($this->marta->notifications()->count())->toBe(3);
});

it('deja historial de etapas en cada postulación de la demo, sin duplicarlo al repetirse', function () {
    $this->seed(DatabaseSeeder::class);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $postulaciones = Postulacion::with('etapas')->get();

        expect(PostulacionEtapa::count())->toBe($postulaciones->count());
        $postulaciones->each(fn (Postulacion $p) => expect($p->etapas->last()?->etapa)->toBe($p->etapa));
    });
});

it('avisa y anota solo lo que la aplicación generaría, de la propia empresa', function () {
    $campana = $this->marta->notifications()->latest()->get()->pluck('data.texto')->all();
    expect($campana[0])->toBe('Marco Díaz postuló a Desarrollador Backend.')
        ->and($campana[1])->toStartWith('Nuevo CV analizado de Elena Ruiz:')
        ->and($campana[2])->toStartWith('Nuevo CV analizado de Sofía Nieto:')
        ->and(implode(' ', $campana))->not->toContain('Orbita');

    $actividad = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Actividad::latest()->pluck('descripcion')->all());
    expect($actividad[0])->toBe('Sofía Nieto avanzó a la etapa de oferta.')
        ->and($actividad[1])->toStartWith('Diego Ferro programó una entrevista con Elena Ruiz para el ')
        ->and($actividad[2])->toBe('Marta Solís aprobó la evaluación de Elena Ruiz.')
        ->and($actividad[3])->toBe('Marco Díaz postuló a Desarrollador Backend.');
});
