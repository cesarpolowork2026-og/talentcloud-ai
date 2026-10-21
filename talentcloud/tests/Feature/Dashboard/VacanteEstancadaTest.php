<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Livewire\Dashboard\Panel;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Dashboard\DashboardService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Alerta de vacante estancada: abierta, con candidatos en curso y sin movimiento (postulación recibida,
 * cambio de etapa o entrevista) en 14 días o más. Fecha fija: martes 13 de octubre de 2026.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 12:00:00');

    $this->nimbus = Empresa::factory()->create();
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    // El embudo, la tendencia y la atención por vacante son del dashboard de gerencia (la cuenta de empresa).
    actuarComo(User::factory()->deEmpresa($this->nimbus)->empresa()->create());
});

afterEach(fn () => Carbon::setTestNow());

/** Una postulación que llegó y se movió por última vez hace `$haceDias` días. */
function postulacionHace(Vacante $vacante, int $haceDias, EtapaPostulacion $etapa = EtapaPostulacion::EnRevision, array $extra = []): Postulacion
{
    $fecha = Carbon::now()->subDays($haceDias);

    return Postulacion::factory()->paraVacante($vacante)->create(array_merge([
        'etapa' => $etapa,
        'created_at' => $fecha,
        'etapa_cambiada_at' => $fecha,
    ], $extra));
}

function estancadasHoy(): array
{
    return app(DashboardService::class)->resumen()->estancadas;
}

it('marca una vacante abierta con candidatos en curso y sin movimiento en 14 días', function () {
    postulacionHace($this->backend, 14);

    expect(estancadasHoy())->toBe([$this->backend->id => 14]);

    Livewire::test(Panel::class)
        ->assertSee('Desarrollador Backend lleva 14 días sin avances')
        ->assertSee('Sin avances · 14 d');
});

it('con un movimiento reciente no está estancada, y el límite es de 14 días', function () {
    postulacionHace($this->backend, 13);
    expect(estancadasHoy())->toBe([]);

    postulacionHace($this->backend, 40);                       // una vieja no la salva ni la condena
    expect(estancadasHoy())->toBe([]);

    Livewire::test(Panel::class)->assertDontSee('días sin avances')->assertDontSee('Sin avances ·');
});

it('una postulación nueva o un cambio de etapa recientes cuentan como movimiento', function () {
    postulacionHace($this->backend, 30);
    postulacionHace($this->backend, 2, EtapaPostulacion::Postulado, ['etapa_cambiada_at' => null]); // llegó hace 2 días

    expect(estancadasHoy())->toBe([]);

    Postulacion::query()->where('etapa', EtapaPostulacion::Postulado)->delete();
    $vieja = postulacionHace($this->backend, 30);
    $vieja->forceFill(['etapa_cambiada_at' => Carbon::now()->subDays(3)])->save();                  // se movió hace 3 días

    expect(estancadasHoy())->toBe([]);
});

it('una entrevista reciente o próxima cuenta como movimiento', function () {
    $postulacion = postulacionHace($this->backend, 30, EtapaPostulacion::Entrevista);

    expect(estancadasHoy())->toHaveCount(1);

    Entrevista::factory()->create([
        'postulacion_id' => $postulacion->id,
        'entrevistador_id' => $this->marta->id,
        'inicia_at' => Carbon::now()->addDays(2),
    ]);

    expect(estancadasHoy())->toBe([]);
});

it('sin candidatos en curso no hay nada que mover: contratados, descartados y retirados no cuentan', function () {
    postulacionHace($this->backend, 30, EtapaPostulacion::Contratado);
    postulacionHace($this->backend, 30, EtapaPostulacion::Descartado);
    postulacionHace($this->backend, 30, EtapaPostulacion::Descartado, ['retirada_at' => Carbon::now()->subDays(20)]);

    expect(estancadasHoy())->toBe([]);
});

it('solo las vacantes abiertas pueden estar estancadas', function () {
    $cerrada = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create();
    postulacionHace($cerrada, 30);

    expect(estancadasHoy())->toBe([]);
});

it('ordena por las más antiguas y la atención muestra tres como máximo', function () {
    $vacantes = collect([20, 35, 50, 28])->map(function (int $dias) {
        $vacante = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();
        postulacionHace($vacante, $dias);

        return $vacante;
    });

    expect(array_values(estancadasHoy()))->toBe([50, 35, 28, 20]);

    $avisos = collect(app(DashboardService::class)->resumen()->atencion)->filter(fn ($a) => str_contains($a['texto'], 'sin avances'));

    expect($avisos)->toHaveCount(3)
        ->and($avisos->first()['parametros'])->toBe(['vacante' => $vacantes[2]->id])
        ->and($avisos->first()['ruta'])->toBe('seleccion.show');
});

it('solo cuenta las vacantes de la empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $otra = Empresa::factory()->create();
        $vacante = Vacante::factory()->deEmpresa($otra)->enEstado(EstadoVacante::Abierta)->create();
        postulacionHace($vacante, 40);
    });

    expect(estancadasHoy())->toBe([]);

    actuarComo(User::factory()->admin()->create());
    expect(estancadasHoy())->toHaveCount(1);
});

it('al admin SaaS le dice de qué empresa es cada aviso; a la empresa, no hace falta', function () {
    postulacionHace($this->backend, 20);
    $sin = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista', 'publicada_at' => Carbon::now()->subDays(10)]);
    $espera = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Diseñador']);
    postulacionHace($espera, 5, EtapaPostulacion::Postulado, ['etapa_cambiada_at' => null]);

    $textos = fn () => collect(app(DashboardService::class)->resumen()->atencion)->pluck('texto')->implode("\n");

    expect($textos())->toContain('Desarrollador Backend lleva 20 días sin avances')
        ->and($textos())->toContain('Analista lleva más de 7 días abierta')
        ->and($textos())->toContain('Diseñador: 1 postulación lleva')
        ->and($textos())->not->toContain('Nimbus');

    actuarComo(User::factory()->admin()->create());

    expect($textos())->toContain($this->backend->titulo.' · '.$this->nimbus->nombre.' lleva 20 días sin avances')
        ->and($textos())->toContain('Analista · '.$this->nimbus->nombre.' lleva más de 7 días abierta')
        ->and($textos())->toContain('Diseñador · '.$this->nimbus->nombre.': 1 postulación lleva');
});
