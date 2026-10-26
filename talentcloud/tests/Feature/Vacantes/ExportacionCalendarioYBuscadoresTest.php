<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Exports\VacantesExport;
use App\Livewire\Vacantes\Listado;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/*
 * Exportación a Excel con los filtros vigentes (RF-26), «Agregar al calendario» del postulante (RF-22)
 * y lo que leen los buscadores: sitemap y datos estructurados de cada vacante abierta (HU28).
 */

beforeEach(function () {
    $this->empresa = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->abierta = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista de Datos']);
    $this->borrador = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Borrador)->create(['titulo' => 'Diseñador UX']);
});

it('exporta a Excel exactamente las vacantes que muestra el listado con su búsqueda', function () {
    Excel::fake();
    actuarComo(User::factory()->deEmpresa($this->empresa)->reclutador()->create());

    Livewire::test(Listado::class)->set('busqueda', 'Analista')->call('exportar');

    Excel::assertDownloaded('vacantes-'.now()->format('Y-m-d').'.xlsx', function (VacantesExport $export) {
        $titulos = $export->query()->pluck('titulo')->all();

        return $titulos === ['Analista de Datos'];
    });
});

it('el postulante descarga su entrevista como evento de calendario, y no la de otra persona', function () {
    $postulante = User::factory()->postulante()->create();
    $propia = Postulacion::factory()->paraVacante($this->abierta)->create(['candidato_id' => Candidato::factory()->conCuenta($postulante)->create()->id]);
    $entrevista = Entrevista::factory()->create(['postulacion_id' => $propia->id, 'estado' => EstadoEntrevista::Confirmada]);
    $ajena = Entrevista::factory()->create(['postulacion_id' => Postulacion::factory()->paraVacante($this->abierta)->create()->id]);

    $respuesta = $this->actingAs($postulante)->get(route('postulante.entrevistas.calendario', $entrevista->id));

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($respuesta->getContent())->toContain('BEGIN:VEVENT')->toContain('Analista de Datos');
    $this->actingAs($postulante)->get(route('postulante.entrevistas.calendario', $ajena->id))->assertNotFound();
});

it('el sitemap lista las vacantes abiertas y su ficha pública declara la oferta de empleo', function () {
    $sitemap = $this->get('/sitemap.xml');

    $sitemap->assertOk()
        ->assertSee(route('empleos.show', $this->abierta->id), false)
        ->assertDontSee(route('empleos.show', $this->borrador->id), false);
    $this->get(route('empleos.show', $this->abierta->id))
        ->assertOk()
        ->assertSee('"@type":"JobPosting"', false)
        ->assertSee('Analista de Datos');
});
