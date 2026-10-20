<?php

use App\DTO\EmbudoDeSeleccionData;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\PlanEmpresa;
use App\Livewire\Dashboard\Panel;
use App\Livewire\Empresas\Listado as Empresas;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * Correcciones de la revisión del administrador SaaS: el panel de la plataforma no filtra variables de sus tablas a la
 * tarjeta «Tu plan» de una empresa, las solicitudes de plan se atienden con un botón y el embudo no redondea a cero.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        // Una empresa que pidió pasar de Pro a Enterprise y otra pegada al límite de su plan.
        $this->nuna = Empresa::factory()->create([
            'nombre' => 'Nuna Salud',
            'plan' => PlanEmpresa::Pro,
            'plan_solicitado' => PlanEmpresa::Enterprise,
            'plan_solicitado_at' => now(),
        ]);
        $this->ruta = Empresa::factory()->create(['nombre' => 'Ruta Sur', 'plan' => PlanEmpresa::Starter]);
        Vacante::factory()->count(3)->deEmpresa($this->ruta)->enEstado(EstadoVacante::Abierta)->create();
    });

    $this->admin = User::factory()->admin()->create();
    actuarComo($this->admin);
});

it('no muestra al admin la tarjeta «Tu plan» con datos de las tablas de empresas', function () {
    Livewire::test(Panel::class)
        ->assertSee('Solicitudes de cambio de plan')
        ->assertSee('Cerca del límite de su plan')
        ->assertDontSee('Tu plan');
});

it('atiende una solicitud de plan con «Aplicar plan», que abre el cambio con el plan pedido propuesto', function () {
    Livewire::test(Panel::class)
        ->assertSee('Aplicar plan')
        ->assertSeeHtml('cambiar-plan='.$this->nuna->id);

    Livewire::withQueryParams(['cambiar-plan' => $this->nuna->id])
        ->test(Empresas::class)
        ->assertSet('mostrarCambioDePlan', true)
        ->assertSet('empresaSeleccionada', $this->nuna->id)
        ->assertSet('planNuevo', PlanEmpresa::Enterprise->value);
});

it('el embudo escribe «menos de 1» y no «0» cuando la conversión es menor al 1 %', function () {
    $etapas = fn (int $contratados) => [
        ['etapa' => EtapaPostulacion::Postulado, 'total' => 1664, 'porcentaje' => 100, 'conversion' => null],
        ['etapa' => EtapaPostulacion::Contratado, 'total' => $contratados, 'porcentaje' => 0, 'conversion' => 0],
    ];
    $embudo = fn (int $contratados, int $total = 1664) => new EmbudoDeSeleccionData(
        dias: 30, desde: now()->subDays(29), hasta: now(), total: $total, etapas: $etapas($contratados),
        descartadas: 0, retiradas: 0, diasHastaContratar: null, contrataciones: $contratados, porOrigen: [],
    );

    expect($embudo(7)->conversionTotalTexto())->toBe('menos de 1')
        ->and($embudo(0)->conversionTotalTexto())->toBe('0')
        ->and($embudo(50)->conversionTotalTexto())->toBe('3,0')
        ->and($embudo(832)->conversionTotalTexto())->toBe('50')
        ->and($embudo(0, 0)->conversionTotalTexto())->toBeNull();
});
