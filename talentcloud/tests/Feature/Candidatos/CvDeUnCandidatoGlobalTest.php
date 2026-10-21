<?php

use App\Livewire\Candidatos\Detalle;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Cvs\SubirCv;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Un candidato es global: su «CV actual» lo ven y lo analizan todas las empresas con las que tiene un proceso. Subir uno
 * nuevo solo le toca al propio postulante o a la empresa que registró a un candidato sin cuenta; una empresa a la que el
 * postulante solo se postuló no puede reemplazarlo.
 */

beforeEach(function () {
    Storage::fake('cvs');
    Queue::fake();

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->nora = User::factory()->deEmpresa($this->nimbus)->empresa()->create();
    $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create();
    $this->admin = User::factory()->admin()->create();

    $this->backendNimbus = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);
    $this->disenoVela = Vacante::factory()->deEmpresa($this->vela)->create(['titulo' => 'Product Designer']);

    // Con cuenta propia, postuló a las dos empresas.
    $this->marco = User::factory()->postulante()->create(['name' => 'Marco Díaz']);
    $this->conCuenta = Candidato::factory()->conCuenta($this->marco)->create();
    app(TenantContext::class)->ejecutarGlobal(function () {
        Postulacion::factory()->paraVacante($this->backendNimbus)->create(['candidato_id' => $this->conCuenta->id]);
        Postulacion::factory()->paraVacante($this->disenoVela)->create(['candidato_id' => $this->conCuenta->id]);
    });

    // Sin cuenta: lo registró Nimbus.
    $this->deNimbus = Candidato::factory()->registradoPor($this->nimbus)->create(['nombre' => 'Elena Ruiz']);
});

function cvNuevo(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Nuevo CV.pdf', DocumentosDePrueba::pdf("Nuevo CV\nDesarrolladora con Java."));
}

it('la empresa que registró a un candidato sin cuenta puede subirle un CV', function () {
    expect(Gate::forUser($this->marta)->allows('subirCv', $this->deNimbus))->toBeTrue()
        ->and(Gate::forUser($this->nora)->allows('subirCv', $this->deNimbus))->toBeTrue();

    actuarComo($this->marta);

    Livewire::test(Detalle::class, ['candidato' => $this->deNimbus])
        ->assertSee('Subir CV')
        ->set('archivoCv', cvNuevo())
        ->call('subirCv')
        ->assertHasNoErrors();

    expect(Cv::where('candidato_id', $this->deNimbus->id)->count())->toBe(1);
});

it('una empresa a la que el postulante solo se postuló no puede cambiarle el CV', function () {
    // Marco ya tiene su CV; Nimbus y Vela lo ven en sus procesos.
    $original = app(SubirCv::class)->ejecutar($this->conCuenta, cvNuevo(), $this->marco);

    foreach ([$this->marta, $this->nora, $this->clara] as $personal) {
        expect(Gate::forUser($personal)->allows('subirCv', $this->conCuenta))->toBeFalse();

        actuarComo($personal);

        Livewire::test(Detalle::class, ['candidato' => $this->conCuenta])
            ->assertDontSee('Subir nuevo CV')
            ->set('archivoCv', cvNuevo())
            ->call('subirCv')
            ->assertForbidden();
    }

    // Sigue siendo el suyo: nadie lo reemplazó.
    expect(Cv::where('candidato_id', $this->conCuenta->id)->count())->toBe(1)
        ->and($this->conCuenta->fresh()->cvActual->id)->toBe($original->id);
});

it('otra empresa no puede subir CV al candidato que registró Nimbus, ni siquiera viéndolo', function () {
    // Vela solo lo vería si hubiera postulado a una vacante suya; aun así no es quien lo registró.
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->disenoVela)->create(['candidato_id' => $this->deNimbus->id]));

    expect(Gate::forUser($this->clara)->allows('view', $this->deNimbus))->toBeTrue()
        ->and(Gate::forUser($this->clara)->allows('subirCv', $this->deNimbus))->toBeFalse();
});

it('el admin SaaS solo mira: no sube CV', function () {
    expect(Gate::forUser($this->admin)->allows('subirCv', $this->deNimbus))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('subirCv', $this->conCuenta))->toBeFalse();
});

it('el propio postulante conserva el permiso sobre su ficha', function () {
    expect(Gate::forUser($this->marco)->allows('subirCv', $this->conCuenta))->toBeTrue()
        ->and(Gate::forUser($this->marco)->allows('subirCv', $this->deNimbus))->toBeFalse();
});
