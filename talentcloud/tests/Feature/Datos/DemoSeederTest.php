<?php

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoReclamacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Models\Actividad;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use App\Models\Reclamacion;
use App\Models\User;
use App\Models\Vacante;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;

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

it('la demo no trae resultados de matching ni de IA: cada postulación guarda su CV y su etapa', function () {
    app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, function () {
        $columnas = Schema::getColumnListing('postulaciones');

        expect($columnas)->not->toContain('match_score')
            ->and($columnas)->not->toContain('match_detalle');

        // El CV escaneado de Pablo Vega queda como error de lectura; los demás se procesaron.
        $estados = Postulacion::query()
            ->with('candidato.cvActual')
            ->whereHas('vacante', fn ($q) => $q->where('titulo', 'Desarrollador Backend'))
            ->get()
            ->mapWithKeys(fn (Postulacion $p) => [$p->candidato->nombre => $p->candidato->cvActual?->estado_procesamiento?->value]);

        expect($estados['Elena Ruiz'])->toBe('procesado')
            ->and($estados['Pablo Vega'])->toBe('error');
    });
});

it('muestra a Marta solo las vacantes, entrevistas y evaluaciones de Nimbus Corp', function () {
    app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, function () {
        expect(Vacante::orderBy('titulo')->pluck('titulo')->all())->toBe(['Data Analyst', 'Desarrollador Backend'])
            ->and(Entrevista::count())->toBe(5)
            ->and(Evaluacion::orderByDesc('puntaje')->pluck('puntaje')->map(fn ($p) => (string) $p)->all())->toBe(['8.9', '8.6', '7.9']);
    });
});

it('muestra al admin SaaS las quince vacantes de la plataforma', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => expect(Vacante::count())->toBe(15));
});

it('cuenta la historia de las empresas de otros sectores con los mismos servicios', function () {
    $rocio = User::where('email', 'rocio@brioretail.pe')->firstOrFail();

    // Cada reclutador ve solo lo suyo.
    app(TenantContext::class)->ejecutarPara($rocio->empresa_id, fn () => expect(Vacante::orderBy('titulo')->pluck('titulo')->all())
        ->toBe(['Analista de Marketing Digital', 'Desarrollador Frontend', 'Ejecutivo Comercial B2B', 'Jefe de Tienda']));

    app(TenantContext::class)->ejecutarGlobal(function () {
        $etapaDe = fn (string $email) => Postulacion::whereHas('candidato', fn ($q) => $q->where('email', $email))->firstOrFail();

        // Contrataciones y vacantes cerradas.
        expect($etapaDe('manuel.quispe@correo.com')->etapa)->toBe(EtapaPostulacion::Contratado)
            ->and($etapaDe('carla.mendoza@correo.com')->etapa)->toBe(EtapaPostulacion::Contratado)
            ->and($etapaDe('analucia.ramos@correo.com')->etapa)->toBe(EtapaPostulacion::Contratado)
            ->and(Vacante::where('titulo', 'Jefe de Almacén')->firstOrFail()->estado)->toBe(EstadoVacante::Cerrada);

        // Milagros retiró su postulación; Pamela seguía en revisión cuando cerró la vacante.
        $milagros = $etapaDe('milagros.huaman@correo.com');
        expect($milagros->estaRetirada())->toBeTrue()
            ->and(EstadoDeMiPostulacion::de($milagros))->toBe(EstadoDeMiPostulacion::Retirada)
            ->and($etapaDe('pamela.rojas@correo.com')->estado_avisado)->toBe(EstadoDeMiPostulacion::Cerrada);
    });

    // Altura Café se registró hace cinco días (en la prueba del plan Pro); Nuna Salud pidió el plan Enterprise.
    $altura = Empresa::where('nombre', 'Altura Café')->firstOrFail();
    $nuna = Empresa::where('nombre', 'Nuna Salud')->firstOrFail();
    expect($altura->enPrueba())->toBeTrue()
        ->and($nuna->plan_solicitado)->toBe(PlanEmpresa::Enterprise);

    // Libro de Reclamaciones: una hoja respondida y otra pendiente.
    expect(Reclamacion::orderBy('numero')->pluck('estado')->all())->toBe([EstadoReclamacion::Respondida, EstadoReclamacion::Pendiente]);
});

it('se puede ejecutar dos veces sin duplicar datos', function () {
    $antes = [PostulacionEtapa::count(), $this->marta->notifications()->count(), app(TenantContext::class)->ejecutarGlobal(fn () => Actividad::count())];

    $this->seed(DatabaseSeeder::class);

    app(TenantContext::class)->ejecutarGlobal(function () {
        expect(User::count())->toBe(24)
            ->and(Vacante::count())->toBe(15)
            ->and(Postulacion::count())->toBe(42);
    });
    expect([PostulacionEtapa::count(), $this->marta->notifications()->count(), app(TenantContext::class)->ejecutarGlobal(fn () => Actividad::count())])->toBe($antes);
});

it('deja la trazabilidad completa de cada postulación, en orden y con sus fechas', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach (Postulacion::with(['etapas', 'candidato.cvs', 'vacante', 'entrevistas', 'evaluaciones'])->get() as $p) {
            $quien = $p->candidato->nombre.' en '.$p->vacante->titulo;
            $etapas = $p->etapas;

            // El historial empieza al postular, en Postulado, avanza en el tiempo y termina en la etapa actual.
            expect($etapas->first()->etapa)->toBe(EtapaPostulacion::Postulado, "Historial sin inicio: {$quien}")
                ->and($etapas->first()->desde->equalTo($p->created_at))->toBeTrue("Inicio del historial distinto de la postulación: {$quien}")
                ->and($etapas->last()->etapa)->toBe($p->etapa)
                ->and($etapas->last()->desde->equalTo($p->etapa_cambiada_at))->toBeTrue("Fecha de etapa distinta del historial: {$quien}")
                ->and($etapas->pluck('desde')->map->timestamp->all())->toBe($etapas->pluck('desde')->map->timestamp->sort()->values()->all());

            // Consentimiento, CV y vacante publicada antes de postular.
            expect($p->candidato->consentimiento_at->lessThanOrEqualTo($p->created_at))->toBeTrue("Consentimiento posterior: {$quien}")
                ->and($p->cv_id)->not->toBeNull()
                ->and($p->candidato->cvs->firstWhere('id', $p->cv_id)->created_at->lessThanOrEqualTo($p->created_at))->toBeTrue("CV posterior a la postulación: {$quien}")
                ->and($p->vacante->publicada_at->lessThanOrEqualTo($p->created_at))->toBeTrue("Postuló antes de la publicación: {$quien}");

            // Cada entrevista se programó antes de celebrarse y cada evaluación, después de una entrevista realizada.
            $p->entrevistas->each(fn ($e) => expect($e->created_at->lessThan($e->inicia_at))->toBeTrue("Entrevista programada después de celebrarse: {$quien}"));
            $p->evaluaciones->each(fn ($e) => expect($p->entrevistas->contains(fn ($x) => $x->estado === EstadoEntrevista::Completada && $x->inicia_at->lessThan($e->created_at)))->toBeTrue("Evaluación sin entrevista previa: {$quien}"));

            // Lo que se le comunicó al candidato coincide con lo que la aplicación le avisaría hoy.
            $aAvisar = EstadoDeMiPostulacion::aAvisar($p);
            if ($aAvisar !== null) {
                expect($p->estado_avisado)->toBe($aAvisar, "Aviso pendiente o distinto: {$quien}");
            }

            // La actividad de la postulación está ordenada en el tiempo y empieza al postular.
            $actividad = Actividad::where('sujeto_type', $p->getMorphClass())->where('sujeto_id', $p->id)->orderBy('created_at')->orderBy('id')->get();
            expect($actividad)->not->toBeEmpty("Postulación sin actividad: {$quien}")
                ->and($actividad->first()->created_at->equalTo($p->created_at))->toBeTrue("La actividad no empieza al postular: {$quien}")
                ->and($actividad->every(fn (Actividad $a) => $a->created_at->lessThanOrEqualTo(now())))->toBeTrue();
        }
    });
});

it('anota la historia en orden cronológico (el reloj de la demo nunca retrocede)', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach ([Actividad::orderBy('id')->pluck('created_at'), PostulacionEtapa::orderBy('id')->pluck('desde')] as $fechas) {
            $marcas = $fechas->map->timestamp->all();
            $ordenadas = $marcas;
            sort($ordenadas);

            expect($marcas)->toBe($ordenadas);
        }
    });
});

it('avisa y anota solo lo que la aplicación generaría, de la propia empresa', function () {
    // Campana de Marta: Marco postuló a su vacante; Diego le asignó la primera entrevista de Lucía.
    $campana = $this->marta->notifications()->latest()->get()->pluck('data.texto')->all();
    expect($campana)->toHaveCount(2)
        ->and($campana[0])->toBe('Marco Díaz postuló a Desarrollador Backend.')
        ->and($campana[1])->toStartWith('Entrevista con Lucía Prat para Data Analyst')
        ->and(implode(' ', $campana))->not->toContain('Iván')->not->toContain('Orbita');

    $actividad = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Actividad::latest()->latest('id')->pluck('descripcion')->all());
    expect(array_slice($actividad, 0, 4))->toBe([
        'Diego Ferro programó una entrevista con Elena Ruiz para el '.Formato::fechaHora(today()->addDays(2)->setTime(15, 0)).'.',
        'Sofía Nieto avanzó a la etapa de oferta.',
        'Lucía Prat avanzó a la etapa de oferta.',
        'Marta Solís aprobó la evaluación de Elena Ruiz.',
    ])->and($actividad)->toContain('Marta Solís registró a Elena Ruiz en Desarrollador Backend.')
        ->and($actividad)->toContain('Marco Díaz postuló a Desarrollador Backend.')
        ->and(implode(' ', $actividad))->not->toContain('Orbita');

    // Marco: en revisión en DevOps y, después, el cierre de esa vacante.
    $marco = User::where('email', 'marco@correo.com')->firstOrFail();
    expect($marco->notifications()->reorder('created_at')->get()->pluck('data.texto')->all())->sequence(
        fn ($t) => $t->toContain('DevOps Engineer')->toContain('revisando'),
        fn ($t) => $t->toContain('DevOps Engineer')->toContain('se cerró'),
    );
});
