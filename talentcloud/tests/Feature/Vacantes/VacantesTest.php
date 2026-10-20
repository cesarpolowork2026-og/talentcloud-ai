<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Livewire\Vacantes\Detalle;
use App\Livewire\Vacantes\Listado;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();

    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create([
        'titulo' => 'Desarrollador Backend', 'ubicacion' => 'Remoto', 'created_at' => Carbon::parse('2026-09-02 09:00'),
    ]);
    $this->data = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::EnRevision)->create([
        'titulo' => 'Data Analyst', 'created_at' => Carbon::parse('2026-09-10 09:00'),
    ]);
    $this->designer = Vacante::factory()->deEmpresa($this->vela)->create(['titulo' => 'Product Designer']);

    Postulacion::factory()->count(2)->paraVacante($this->backend)->create();

    actuarComo($this->marta);
});

it('muestra la pantalla Vacantes del prototipo', function () {
    $this->get('/vacantes')
        ->assertOk()
        ->assertSee('+ Nueva vacante')
        ->assertSee('Buscar vacante o empresa…')
        ->assertSeeInOrder(['Todas', 'Abierta', 'En revisión', 'Borrador', 'Cerrada'])
        ->assertSeeInOrder(['Vacante', 'Empresa', 'Ubicación', 'Candidatos', 'Estado', 'Creada'])
        ->assertSee('2 sep');
});

it('muestra solo las vacantes de la propia empresa', function () {
    Livewire::test(Listado::class)
        ->assertSeeHtml('>Desarrollador Backend</button>')
        ->assertSeeHtml('>Data Analyst</button>')
        ->assertDontSeeHtml('>Product Designer</button>');
});

it('muestra al admin SaaS las vacantes de todas las empresas', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Listado::class)
        ->assertSee('Todas las vacantes publicadas por las empresas de la plataforma.')
        ->assertSee('Product Designer')
        ->assertSee('Desarrollador Backend');
});

it('niega Vacantes al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/vacantes')->assertForbidden();
});

it('filtra, busca y ordena por candidatos', function () {
    Livewire::test(Listado::class)
        ->call('filtrarEstado', 'en_revision')
        ->assertSeeHtml('>Data Analyst</button>')
        ->assertDontSeeHtml('>Desarrollador Backend</button>')
        ->call('filtrarEstado', '')
        ->set('busqueda', 'backend')
        ->assertSeeHtml('>Desarrollador Backend</button>')
        ->assertDontSeeHtml('>Data Analyst</button>')
        ->set('busqueda', '')
        ->set('orden', 'candidatos')
        ->call('alternarDireccion')
        ->assertSeeInOrder(['Desarrollador Backend', 'Data Analyst']);
});

it('muestra la vista previa del prototipo', function () {
    Livewire::test(Listado::class)
        ->call('verVistaPrevia', $this->backend->id)
        ->assertSee('Nimbus Corp · Remoto')
        ->assertSee('2 candidatos · Estado: Abierta');
});

it('no muestra la vista previa de una vacante de otra empresa', function () {
    Livewire::test(Listado::class)->call('verVistaPrevia', $this->designer->id)->assertNotFound();
});

it('crea una vacante en borrador con las competencias como requisitos', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'QA Engineer')
        ->set('modalidad', 'hibrido')
        ->set('ubicacion', 'Lima')
        ->set('competencias', ['Selenium', 'cypress ', 'selenium', 'Testing de APIs'])
        ->call('crear')
        ->assertHasNoErrors()
        ->assertRedirect();

    $vacante = Vacante::where('titulo', 'QA Engineer')->firstOrFail();

    expect($vacante->empresa_id)->toBe($this->nimbus->id)
        ->and($vacante->estado)->toBe(EstadoVacante::Borrador)
        ->and($vacante->creada_por)->toBe($this->marta->id)
        ->and($vacante->requisitos()->with('competencia')->get()->pluck('competencia.nombre')->all())->toBe(['Selenium', 'cypress', 'Testing de APIs']);
});

it('exige ubicación en una vacante presencial o híbrida, pero no en una remota', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'QA Presencial')
        ->set('modalidad', 'presencial')
        ->set('competencias', ['Selenium'])
        ->call('crear')
        ->assertHasErrors(['ubicacion']);

    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'QA Remoto')
        ->set('modalidad', 'remoto')
        ->set('competencias', ['Selenium'])
        ->call('crear')
        ->assertHasNoErrors();

    expect(Vacante::where('titulo', 'QA Presencial')->exists())->toBeFalse()
        ->and(Vacante::where('titulo', 'QA Remoto')->firstOrFail()->ubicacion)->toBeNull();
});

it('en una vacante remota la ubicación es una cobertura opcional y se muestra junto a la modalidad', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'Soporte')
        ->set('modalidad', 'remoto')
        ->set('ubicacion', ' Perú ')
        ->set('competencias', ['Soporte técnico'])
        ->call('crear')
        ->assertHasNoErrors();

    $soporte = Vacante::where('titulo', 'Soporte')->firstOrFail();
    $presencial = Vacante::factory()->make(['modalidad' => Modalidad::Presencial, 'ubicacion' => 'Lima']);
    $remota = Vacante::factory()->make(['modalidad' => Modalidad::Remoto, 'ubicacion' => 'Remoto']);

    expect($soporte->lugarYModalidad())->toBe('Perú · Remoto')
        ->and($presencial->lugarYModalidad())->toBe('Lima · Presencial')
        ->and($remota->lugarYModalidad())->toBe('Remoto');
});

it('al cambiar a remoto en el formulario se descarta la ubicación escrita', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('modalidad', 'presencial')
        ->set('ubicacion', 'Lima')
        ->set('modalidad', 'remoto')
        ->assertSet('ubicacion', '')
        ->set('modalidad', 'hibrido')
        ->set('ubicacion', 'Cusco')
        ->set('modalidad', 'presencial')
        ->assertSet('ubicacion', 'Cusco');
});

it('exige al menos una competencia y reconoce sinónimos del catálogo sin duplicarlas', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'Analista BI')
        ->set('competencias', [])
        ->call('crear')
        ->assertHasErrors(['competencias']);

    $powerBi = Competencia::obtenerOCrear('Power BI');
    $powerBi->update(['sinonimos' => ['PowerBI']]);

    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('titulo', 'Analista BI')
        ->set('competencias', ['PowerBI', 'power bi', 'SQL'])
        ->call('crear')
        ->assertHasNoErrors();

    $vacante = Vacante::where('titulo', 'Analista BI')->firstOrFail();

    expect($vacante->requisitos()->with('competencia')->get()->pluck('competencia.nombre')->all())->toBe(['Power BI', 'SQL'])
        ->and(Competencia::where('nombre_normalizado', 'powerbi')->exists())->toBeFalse();
});

it('no deja al admin crear vacantes: solo las supervisa', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Listado::class)
        ->set('titulo', 'QA')
        ->set('empresaId', (string) $this->vela->id)
        ->set('competencias', ['Selenium'])
        ->call('crear')
        ->assertForbidden();

    expect(Vacante::sinFiltroDeEmpresa()->where('titulo', 'QA')->exists())->toBeFalse();
});

it('abre el detalle de su vacante y da 404 con la de otra empresa', function () {
    $this->get('/vacantes/'.$this->backend->id)->assertOk()->assertSee('Desarrollador Backend')->assertSee('Requisitos');
    $this->get('/vacantes/'.$this->designer->id)->assertNotFound();
});

it('gestiona requisitos con alta rápida de competencias', function () {
    $componente = Livewire::test(Detalle::class, ['vacante' => $this->backend])
        ->set('competencia', 'Rust')
        ->set('tipo', 'deseable')
        ->set('peso', '4')
        ->set('aniosMinimos', '2')
        ->call('agregarRequisito')
        ->assertHasNoErrors();

    $requisito = Requisito::where('vacante_id', $this->backend->id)->firstOrFail();

    expect(Competencia::where('nombre', 'Rust')->exists())->toBeTrue()
        ->and($requisito->tipo)->toBe(TipoRequisito::Deseable)
        ->and($requisito->peso)->toBe(4)
        ->and($requisito->anios_minimos)->toBe(2)
        ->and($requisito->empresa_id)->toBe($this->nimbus->id);

    // No se repite la misma competencia (sin importar mayúsculas).
    $componente->set('competencia', 'rust')->call('agregarRequisito')->assertHasErrors(['competencia']);

    $componente->call('editarRequisito', $requisito->id)
        ->set('peso', '5')
        ->set('detalleRequisito', 'Servicios de alto rendimiento')
        ->call('guardarRequisito')
        ->assertHasNoErrors();

    expect($requisito->fresh()->peso)->toBe(5);

    $componente->call('confirmarQuitarRequisito', $requisito->id)->call('quitarRequisito');

    expect(Requisito::where('vacante_id', $this->backend->id)->exists())->toBeFalse();
});

it('recorre el ciclo de vida de la vacante', function () {
    $borrador = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Borrador)->create(['titulo' => 'Nueva']);

    $componente = Livewire::test(Detalle::class, ['vacante' => $borrador])
        ->call('cambiarEstado', 'abierta')
        ->assertHasErrors(['estado']);

    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Borrador);

    Requisito::factory()->create(['vacante_id' => $borrador->id]);

    $componente->call('cambiarEstado', 'abierta')->assertHasNoErrors();
    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Abierta)
        ->and($borrador->fresh()->publicada_at)->not->toBeNull();

    $componente->call('cambiarEstado', 'en_revision');
    expect($borrador->fresh()->estado)->toBe(EstadoVacante::EnRevision);

    $componente->call('cambiarEstado', 'cerrada');
    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Cerrada);

    // Cerrada es final y no se puede modificar.
    $componente->call('cambiarEstado', 'abierta')->assertHasErrors(['estado']);
    $componente->set('titulo', 'Cambiado')->call('guardarDatos')->assertHasErrors(['titulo']);

    expect($borrador->fresh()->estado)->toBe(EstadoVacante::Cerrada)
        ->and($borrador->fresh()->titulo)->toBe('Nueva');
});

it('guarda los datos de la vacante', function () {
    Livewire::test(Detalle::class, ['vacante' => $this->backend])
        ->set('descripcion', 'APIs REST con Java.')
        ->set('modalidad', 'hibrido')
        ->call('guardarDatos')
        ->assertHasNoErrors()
        ->assertDispatched('vacante-guardada');

    expect($this->backend->fresh()->descripcion)->toBe('APIs REST con Java.');
});

it('no cierra una vacante con entrevistas por realizar y nombra a quién', function () {
    $elena = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id]);
    Entrevista::factory()->count(2)->sequence(['inicia_at' => now()->addDay()], ['inicia_at' => now()->addDays(2)])
        ->create(['postulacion_id' => $elena->id, 'estado' => EstadoEntrevista::Pendiente]);

    Livewire::test(Detalle::class, ['vacante' => $this->backend])
        ->call('cambiarEstado', 'cerrada')
        ->assertHasErrors('estado')
        ->assertSee('La vacante tiene 2 entrevistas por realizar (Elena Ruiz). Cancélalas en «Entrevistas» antes de cerrarla.');

    expect($this->backend->fresh()->estado)->not->toBe(EstadoVacante::Cerrada);
});
