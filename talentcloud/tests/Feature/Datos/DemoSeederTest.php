<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Models\Actividad;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;

/*
 * El seeder de demostración deja la app con los datos del prototipo y
 * respeta el aislamiento entre empresas.
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

it('reproduce el Kanban de Desarrollador Backend del prototipo', function () {
    $kanban = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Postulacion::query()
        ->with('candidato')
        ->whereHas('vacante', fn ($q) => $q->where('titulo', 'Desarrollador Backend'))
        ->orderBy('orden')
        ->get()
        ->groupBy(fn (Postulacion $p) => $p->etapa->value)
        ->map(fn ($columna) => $columna->mapWithKeys(fn (Postulacion $p) => [$p->candidato->nombre => $p->matchPorcentaje()])->all())
        ->all());

    expect(array_map('array_keys', $kanban))->toEqual([
        EtapaPostulacion::Postulado->value => ['Elena Ruiz', 'Marco Díaz'],
        EtapaPostulacion::EnRevision->value => ['Sofía Nieto'],
        EtapaPostulacion::Entrevista->value => ['Iván Torres'],
        EtapaPostulacion::Oferta->value => ['Lucía Prat'],
        EtapaPostulacion::Descartado->value => ['Pablo Vega'],
    ]);
});

it('calcula el matching de la demo con el motor a partir de los CV', function () {
    $match = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Postulacion::query()
        ->with('candidato')
        ->whereHas('vacante', fn ($q) => $q->where('titulo', 'Desarrollador Backend'))
        ->get()
        ->mapWithKeys(fn (Postulacion $p) => [$p->candidato->nombre => $p->matchPorcentaje()]));

    // Los porcentajes son los del motor, no números fijos: se comprueba su coherencia.
    expect($match['Elena Ruiz'])->toBeGreaterThan($match['Marco Díaz'])
        ->and($match['Lucía Prat'])->toBeGreaterThan($match['Marco Díaz'])
        ->and($match['Sofía Nieto'])->toBeGreaterThan($match['Iván Torres'])
        ->and($match['Pablo Vega'])->toBeNull(); // CV escaneado sin texto
});

it('muestra a Marta solo las vacantes, entrevistas y evaluaciones de Nimbus Corp', function () {
    app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, function () {
        expect(Vacante::orderBy('titulo')->pluck('titulo')->all())->toBe(['Data Analyst', 'Desarrollador Backend'])
            ->and(Entrevista::count())->toBe(4)
            ->and(Evaluacion::orderByDesc('puntaje')->pluck('puntaje')->map(fn ($p) => (string) $p)->all())->toBe(['8.6', '7.9', '5.4', '3.1']);
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
            ->and(Postulacion::count())->toBe(10);
    });
    expect($this->marta->notifications()->count())->toBe(3);
});

it('ordena la campana y la actividad reciente como el prototipo', function () {
    expect($this->marta->notifications()->latest()->get()->pluck('data.texto')->all())->toBe([
        'Nuevo CV analizado para Desarrollador Backend (Elena Ruiz).',
        'Entrevista de Iván Torres pendiente de confirmar.',
        'Orbita Labs actualizó su plan a Starter.',
    ]);

    $actividad = app(TenantContext::class)->ejecutarPara($this->marta->empresa_id, fn () => Actividad::latest()->pluck('descripcion')->all());
    expect($actividad)->toBe([
        'La IA analizó 6 CVs nuevos para Desarrollador Backend.',
        'Sofía Nieto avanzó a la etapa de entrevista.',
        'Marta Solís aprobó la evaluación de Elena Ruiz.',
    ]);
});
