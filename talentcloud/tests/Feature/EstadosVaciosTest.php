<?php

use App\Enums\EstadoVacante;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Postulante\MisPostulaciones;
use App\Livewire\Vacantes\Listado as Vacantes;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * Estados vacíos: la "primera vez" (aún no hay nada) invita a empezar con una
 * ilustración y la acción, solo para quien puede hacerla; "sin resultados" de un
 * filtro se queda en un mensaje.
 */

beforeEach(function () {
    $this->empresa = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create(['nombre' => 'Nueva SAC']));
    $this->cuenta = User::factory()->deEmpresa($this->empresa)->empresa()->create();
    $this->reclutador = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
});

it('invita a publicar la primera vacante a quien puede crearla', function () {
    actuarComo($this->cuenta);

    Livewire::test(Vacantes::class)
        ->assertSee('Publica tu primera vacante')
        ->assertSeeHtml('viewBox="0 0 240 160"')
        ->assertSeeHtml('wire:click="abrirFormulario">+ Nueva vacante</button>');
});

it('distingue la primera vez de una búsqueda sin resultados', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista']));
    actuarComo($this->cuenta);

    Livewire::test(Vacantes::class)
        ->set('busqueda', 'no-existe')
        ->assertSee('No se encontraron vacantes con estos filtros.')
        ->assertDontSee('Publica tu primera vacante');
});

it('muestra la primera vez de candidatos y entrevistas con su acción', function () {
    actuarComo($this->reclutador);

    Livewire::test(Candidatos::class)
        ->assertSee('Aún no hay candidatos')
        ->assertSee('+ Nuevo candidato');

    Livewire::test(Entrevistas::class)
        ->assertSee('Tu agenda está libre')
        ->assertSee('+ Programar entrevista');

    // Con un filtro, solo el mensaje.
    Livewire::test(Entrevistas::class)->set('busqueda', 'zzz')->assertSee('No se encontraron entrevistas.')->assertDontSee('Tu agenda está libre');
});

it('invita al postulante sin postulaciones a ver las vacantes', function () {
    $postulante = User::factory()->postulante()->create();
    actuarComo($postulante);

    Livewire::test(MisPostulaciones::class)
        ->assertSee('Todavía no tienes postulaciones')
        ->assertSee('Ver vacantes abiertas');
});
