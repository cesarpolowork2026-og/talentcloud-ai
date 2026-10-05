<?php

use App\Enums\EstadoVacante;
use App\Livewire\Candidatos\Detalle as FichaCandidato;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Evaluaciones\Listado as Evaluaciones;
use App\Livewire\Kanban\Tablero;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * El admin SaaS ve vacantes y postulantes de todas las empresas: cada pantalla
 * de "Procesos de selección" le indica a qué empresa pertenece cada uno
 * (Formato::vacante en el contexto global). El personal de una empresa solo ve
 * lo suyo y lo ve como siempre.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        $this->disenador = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);

        $this->sofia = Candidato::factory()->create(['nombre' => 'Sofía Nieto']);
        $this->ivan = Candidato::factory()->create(['nombre' => 'Iván Torres']);

        $this->deSofia = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->sofia->id]);
        // Iván está en procesos de las dos empresas; el más reciente es el de Vela.
        $this->ivanEnNimbus = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->ivan->id, 'created_at' => now()->subDay()]);
        $this->ivanEnVela = Postulacion::factory()->paraVacante($this->disenador)->create(['candidato_id' => $this->ivan->id]);
    });

    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->admin = User::factory()->admin()->create();
});

it('añade la empresa al nombre de la vacante solo en la vista global', function () {
    $vacante = $this->backend->load('empresa');

    actuarComo($this->marta);
    expect(Formato::vacante($vacante))->toBe('Desarrollador Backend');

    actuarComo($this->admin);
    expect(Formato::vacante($vacante))->toBe('Desarrollador Backend · Nimbus Corp')
        ->and(Formato::vacante(null))->toBe('');
});

it('muestra al admin la empresa de cada vacante en el selector del Kanban', function () {
    actuarComo($this->admin);

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
    actuarComo($this->admin);

    Livewire::test(Entrevistas::class)
        ->call('abrirFormulario')
        ->assertSeeHtml('>Sofía Nieto — Desarrollador Backend · Nimbus Corp</option>')
        ->assertSeeHtml('>Iván Torres — Product Designer · Vela Studio</option>')
        ->assertSeeHtml('>Iván Torres — Desarrollador Backend · Nimbus Corp</option>');
});

it('muestra al reclutador sus postulantes como siempre al programar una entrevista', function () {
    actuarComo($this->marta);

    Livewire::test(Entrevistas::class)
        ->call('abrirFormulario')
        ->assertSeeHtml('>Sofía Nieto — Desarrollador Backend</option>')
        ->assertDontSee('Product Designer');
});

it('muestra al admin en Candidatos la empresa de la postulación y las demás empresas', function () {
    actuarComo($this->admin);

    Livewire::test(Candidatos::class)
        ->assertSee('Postuló a Product Designer · Vela Studio')
        ->assertSee('También en: Nimbus Corp')
        ->assertSee('Postuló a Desarrollador Backend · Nimbus Corp')
        ->call('verDetalle', $this->ivan->id)
        ->assertSee('Postuló a Product Designer · Vela Studio');
});

it('muestra al reclutador en Candidatos solo lo de su empresa, sin nombres de empresa', function () {
    actuarComo($this->marta);

    Livewire::test(Candidatos::class)
        ->assertSee('Postuló a Desarrollador Backend')
        ->assertDontSee('· Nimbus Corp')
        ->assertDontSee('También en')
        ->assertDontSee('Product Designer');
});

it('muestra al admin la empresa de cada postulación en la ficha del candidato', function () {
    actuarComo($this->admin);

    Livewire::test(FichaCandidato::class, ['candidato' => $this->ivan])
        ->assertSee('Product Designer · Vela Studio')
        ->assertSee('Desarrollador Backend · Nimbus Corp');
});

it('muestra al admin la empresa de la vacante en Entrevistas y Evaluaciones', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        Entrevista::factory()->create(['postulacion_id' => $this->ivanEnVela->id]);
        Evaluacion::factory()->create(['postulacion_id' => $this->ivanEnVela->id]);
    });

    actuarComo($this->admin);
    Livewire::test(Entrevistas::class)->assertSeeHtml('<td>Product Designer · Vela Studio</td>');
    Livewire::test(Evaluaciones::class)->assertSeeHtml('<td>Product Designer · Vela Studio</td>');

    $entrevistaDeNimbus = app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create(['postulacion_id' => $this->deSofia->id]));
    actuarComo($this->marta);
    Livewire::test(Entrevistas::class)->assertSeeHtml('<td>Desarrollador Backend</td>');
    expect($entrevistaDeNimbus->empresa_id)->toBe($this->nimbus->id);
});
