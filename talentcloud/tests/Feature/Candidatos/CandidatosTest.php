<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Livewire\Candidatos\Detalle;
use App\Livewire\Candidatos\Listado;
use App\Livewire\Postulante\MiCurriculo;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Cvs\SubirCv;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('cvs');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();

    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);
    $this->designer = Vacante::factory()->deEmpresa($this->vela)->create(['titulo' => 'Product Designer']);

    // Elena postuló a Nimbus; Iván solo a Vela; Marco a ambas; Rosa la registró Nimbus sin postular.
    $this->elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz', 'titular' => 'Desarrolladora Backend']);
    $this->ivan = Candidato::factory()->create(['nombre' => 'Iván Torres']);
    $this->marco = Candidato::factory()->create(['nombre' => 'Marco Díaz']);
    $this->rosa = Candidato::factory()->registradoPor($this->nimbus)->create(['nombre' => 'Rosa Paredes']);

    Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->elena->id, 'created_at' => now()->subDays(3)]);
    Postulacion::factory()->paraVacante($this->designer)->create(['candidato_id' => $this->ivan->id]);
    Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->marco->id]);
    Postulacion::factory()->paraVacante($this->designer)->create(['candidato_id' => $this->marco->id]);

    actuarComo($this->marta);
});

it('muestra la pantalla Candidatos del prototipo', function () {
    $this->get('/candidatos')
        ->assertOk()
        ->assertSee('CVs recibidos y candidatos de tus vacantes.')
        ->assertSee('Buscar candidato…')
        ->assertSeeInOrder(['Nombre', 'Postulación más reciente'])
        ->assertSee('Desarrolladora Backend')
        ->assertSee('Desarrollador Backend · Postulado')
        ->assertSeeHtml('wire:click.stop="verDetalle(')
        ->assertDontSee('% match');
});

it('muestra solo los candidatos que postularon a la empresa o que ella registró', function () {
    Livewire::test(Listado::class)
        ->assertSee('Elena Ruiz')
        ->assertSee('Marco Díaz')
        ->assertSee('Rosa Paredes')
        ->assertDontSee('Iván Torres');
});

it('muestra a cada empresa solo su propia postulación de un candidato compartido', function () {
    actuarComo(User::factory()->deEmpresa($this->vela)->reclutador()->create());

    Livewire::test(Listado::class)
        ->assertSee('Marco Díaz')
        ->assertSee('Product Designer · Postulado')
        ->assertDontSee('Desarrollador Backend · ')
        ->assertDontSeeHtml('>Elena Ruiz</button>')
        ->assertDontSee('Rosa Paredes');
});

it('muestra al admin SaaS todos los candidatos', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Listado::class)->assertSee('Iván Torres')->assertSee('Rosa Paredes');
});

it('ordena por la postulación más reciente, con los candidatos sin postulación al final', function () {
    // Marco postuló después que Elena. assertSeeInOrder de Livewire compara el JSON (con "í" escapada); se revisa el HTML.
    $html = Livewire::test(Listado::class)
        ->set('orden', 'reciente')
        ->call('alternarDireccion')
        ->html();

    expect(strpos($html, 'Marco Díaz</button>'))->toBeLessThan(strpos($html, 'Elena Ruiz</button>'))
        ->and(strpos($html, 'Elena Ruiz</button>'))->toBeLessThan(strpos($html, 'Rosa Paredes</button>'));
});

it('muestra el estado de error del CV con "Reintentar lectura"', function () {
    app(SubirCv::class)->ejecutar($this->elena, UploadedFile::fake()->createWithContent('escaneado.pdf', DocumentosDePrueba::pdf(null)), null);

    Livewire::test(Listado::class)
        ->assertSee('No pudimos leer el CV')
        ->assertSee('Reintentar lectura')
        ->call('reintentar', $this->elena->cvActual->id)
        ->assertHasNoErrors();
});

it('abre el diálogo del CV con el extracto', function () {
    Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id]);
    app(SubirCv::class)->ejecutar($this->elena, UploadedFile::fake()->createWithContent('CV.docx', DocumentosDePrueba::docx("Elena Ruiz\n5 años de experiencia con Java y Spring Boot en proyectos de alto tráfico.")), null);

    Livewire::test(Listado::class)
        ->call('verDetalle', $this->elena->id)
        ->assertSet('mostrarDetalle', true)
        ->assertDontSee('Coincidencia')
        ->assertSee('Extracto del CV:')
        ->assertSee('5 años de experiencia con Java');
});

it('no abre candidatos de otra empresa', function () {
    Livewire::test(Listado::class)->call('verDetalle', $this->ivan->id)->assertNotFound();
    $this->get('/candidatos/'.$this->ivan->id)->assertForbidden();
});

it('registra un candidato con su CV y lo postula a una vacante abierta', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('nombre', 'Sofía Nieto')
        ->set('titular', 'Desarrolladora Backend')
        ->set('email', 'SOFIA@correo.com')
        ->set('vacanteId', (string) $this->backend->id)
        ->set('archivoCv', UploadedFile::fake()->createWithContent('CV_Sofia.pdf', DocumentosDePrueba::pdf("Sofía Nieto\nJava, Docker y Kubernetes en producción durante seis años.")))
        ->set('autorizado', true)
        ->call('registrar')
        ->assertHasNoErrors()
        ->assertSet('mostrarFormulario', false);

    $sofia = Candidato::where('nombre', 'Sofía Nieto')->firstOrFail();
    $postulacion = Postulacion::where('candidato_id', $sofia->id)->firstOrFail();

    expect($sofia->empresa_origen_id)->toBe($this->nimbus->id)
        ->and($sofia->email)->toBe('sofia@correo.com')
        ->and($sofia->consentimiento_at)->not->toBeNull()
        ->and($sofia->cvActual->texto_extraido)->toContain('Kubernetes')
        ->and($postulacion->vacante_id)->toBe($this->backend->id)
        ->and($postulacion->etapa)->toBe(EtapaPostulacion::Postulado)
        ->and($postulacion->cv_id)->toBe($sofia->cvActual->id);
});

it('exige la autorización del candidato y un archivo válido', function () {
    Livewire::test(Listado::class)
        ->set('nombre', 'X')
        ->set('archivoCv', UploadedFile::fake()->create('virus.exe', 10))
        ->call('registrar')
        ->assertHasErrors(['autorizado', 'archivoCv']);
});

it('no postula a vacantes cerradas ni dos veces a la misma', function () {
    $cerrada = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create();

    Livewire::test(Detalle::class, ['candidato' => $this->rosa])
        ->set('vacanteId', (string) $cerrada->id)
        ->call('postular')
        ->assertHasErrors(['vacanteId'])
        ->set('vacanteId', (string) $this->backend->id)
        ->call('postular')
        ->assertHasNoErrors()
        ->set('vacanteId', (string) $this->backend->id)
        ->call('postular')
        ->assertHasErrors(['vacanteId']);

    expect(Postulacion::where('candidato_id', $this->rosa->id)->count())->toBe(1);
});

it('no postula a vacantes de otra empresa', function () {
    Livewire::test(Detalle::class, ['candidato' => $this->rosa])
        ->set('vacanteId', (string) $this->designer->id)
        ->call('postular')
        ->assertHasErrors(['vacanteId']);
});

it('gestiona las competencias que anota la empresa: se agregan y se quitan', function () {
    $componente = Livewire::test(Detalle::class, ['candidato' => $this->elena])
        ->set('nuevaCompetencia', 'Scrum')
        ->call('agregarCompetencia')
        ->assertHasNoErrors()
        ->assertSee('Scrum')
        ->assertDontSee('· IA');

    $scrum = Competencia::where('nombre', 'Scrum')->firstOrFail();
    $componente->call('quitarCompetencia', $scrum->id);

    expect($this->elena->competencias()->pluck('nombre')->all())->toBe([]);
});

it('muestra "Mi currículo" al postulante y le deja subir un CV nuevo', function () {
    $user = User::factory()->postulante()->create();
    Candidato::factory()->conCuenta($user)->create();
    actuarComo($user);

    $this->get('/postulante/postulaciones')->assertOk()->assertSee('Mi currículo')->assertSee('Aún no subiste tu CV');

    Livewire::test(MiCurriculo::class)
        ->set('archivoCv', UploadedFile::fake()->createWithContent('CV_Marco.pdf', DocumentosDePrueba::pdf("Marco Díaz\nDesarrollador backend junior con Java y MySQL.")))
        ->call('subir')
        ->assertHasNoErrors()
        ->assertSee('CV_Marco.pdf')
        ->assertSee('CV cargado y listo para tus postulaciones');

    expect($user->candidato->cvActual->texto_extraido)->toContain('MySQL');
});
