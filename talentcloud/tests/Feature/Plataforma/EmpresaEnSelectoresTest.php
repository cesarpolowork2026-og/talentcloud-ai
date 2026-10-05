<?php

use App\Enums\EstadoVacante;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Kanban\Tablero;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * El admin SaaS elige entre vacantes y postulantes de todas las empresas: los
 * selectores le indican a qué empresa pertenece cada uno. El personal de una
 * empresa solo ve lo suyo y los ve como siempre.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        $this->disenador = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);

        Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Sofía Nieto'])->id]);
        Postulacion::factory()->paraVacante($this->disenador)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Iván Torres'])->id]);
    });

    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
});

it('muestra al admin la empresa de cada vacante en el selector del Kanban', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->assertSeeHtml('>Desarrollador Backend · Nimbus Corp</option>')
        ->assertSeeHtml('>Product Designer · Vela Studio</option>');
});

it('muestra al reclutador sus vacantes sin el nombre de la empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Data Analyst']));
    actuarComo($this->marta);

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->assertSeeHtml('>Desarrollador Backend</option>')
        ->assertDontSee('· Nimbus Corp')
        ->assertDontSee('Product Designer');
});

it('muestra al admin la empresa de cada postulante al programar una entrevista', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Entrevistas::class)
        ->call('abrirFormulario')
        ->assertSeeHtml('>Sofía Nieto — Desarrollador Backend · Nimbus Corp</option>')
        ->assertSeeHtml('>Iván Torres — Product Designer · Vela Studio</option>');
});

it('muestra al reclutador sus postulantes como siempre al programar una entrevista', function () {
    actuarComo($this->marta);

    Livewire::test(Entrevistas::class)
        ->call('abrirFormulario')
        ->assertSeeHtml('>Sofía Nieto — Desarrollador Backend</option>')
        ->assertDontSee('Iván Torres');
});
