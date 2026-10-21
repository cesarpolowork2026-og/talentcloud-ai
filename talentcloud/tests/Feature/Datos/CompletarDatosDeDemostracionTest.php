<?php

use App\Enums\EstadoVacante;
use App\Enums\Jornada;
use App\Models\Empresa;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CondicionesDeDemostracion;
use Database\Seeders\Demo\PerfilesDeEmpresasDeDemostracion;

/*
 * Jornada, sueldo y descripción de empresas de la demostración: valores inventados que hacen visibles en la bolsa de empleos
 * las funciones recientes. El comando los aplica a una base ya sembrada sin pisar nada que ya esté escrito.
 */

it('todos los rangos del catálogo respetan las reglas de la base', function () {
    foreach (CondicionesDeDemostracion::POR_TITULO as $titulo => [$jornada, $minimo, $maximo]) {
        expect(Jornada::tryFrom($jornada))->not->toBeNull("jornada inválida en «{$titulo}»")
            ->and($minimo)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(1_000_000)
            ->and($maximo === null || $maximo >= $minimo)->toBeTrue("el máximo es menor que el mínimo en «{$titulo}»");
    }
});

it('el comando completa las vacantes y las empresas que no tienen datos, y dice cuántas', function () {
    $empresa = Empresa::factory()->create(['nombre' => 'Brío Retail', 'descripcion' => null]);
    $conocida = Vacante::factory()->deEmpresa($empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Jefe de Tienda', 'jornada' => null, 'sueldo_minimo' => null, 'sueldo_maximo' => null]);
    $aConvenir = Vacante::factory()->deEmpresa($empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista de Marketing Digital', 'jornada' => null, 'sueldo_minimo' => null, 'sueldo_maximo' => null]);

    $this->artisan('talentcloud:completar-datos-demo')
        ->expectsOutput('Vacantes completadas: 1.')
        ->expectsOutput('Empresas completadas: 1.')
        ->assertSuccessful();

    $conocida = app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::find($conocida->id));
    $aConvenir = app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::find($aConvenir->id));

    expect($conocida->jornada)->toBe(Jornada::Completa)
        ->and([$conocida->sueldo_minimo, $conocida->sueldo_maximo])->toBe([3200, 4200])
        ->and($conocida->sueldoEnTexto())->toContain('3 200')
        // Sin entrada en el catálogo queda «a convenir»: la bolsa muestra las dos situaciones.
        ->and($aConvenir->jornada)->toBeNull()->and($aConvenir->sueldo_minimo)->toBeNull()
        ->and($empresa->fresh()->descripcion)->toBe(PerfilesDeEmpresasDeDemostracion::POR_EMPRESA['Brío Retail']);
});

it('es idempotente y no pisa lo que ya está escrito', function () {
    $empresa = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'descripcion' => 'Descripción escrita por la empresa.']);
    $propia = Vacante::factory()->deEmpresa($empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend', 'jornada' => 'parcial', 'sueldo_minimo' => 3000, 'sueldo_maximo' => 3500]);

    $this->artisan('talentcloud:completar-datos-demo')->expectsOutput('Vacantes completadas: 0.')->expectsOutput('Empresas completadas: 0.')->assertSuccessful();
    $this->artisan('talentcloud:completar-datos-demo')->expectsOutput('Vacantes completadas: 0.')->assertSuccessful();

    $propia = app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::find($propia->id));

    expect($propia->jornada)->toBe(Jornada::Parcial)
        ->and([$propia->sueldo_minimo, $propia->sueldo_maximo])->toBe([3000, 3500])
        ->and($empresa->fresh()->descripcion)->toBe('Descripción escrita por la empresa.');
});

it('la bolsa pública muestra el sueldo y la descripción de la empresa tras completar los datos', function () {
    $empresa = Empresa::factory()->create(['nombre' => 'Altura Café', 'descripcion' => null]);
    $vacante = Vacante::factory()->deEmpresa($empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Mozo(a)', 'jornada' => null, 'sueldo_minimo' => null, 'sueldo_maximo' => null]);

    $this->artisan('talentcloud:completar-datos-demo')->assertSuccessful();

    $this->get(route('empleos.show', $vacante->id))
        ->assertOk()
        ->assertSee('1 130')
        ->assertSee('Cafeterías de especialidad con granos de altura');
});
