<?php

use App\Enums\EstadoVacante;
use App\Livewire\Candidatos\Detalle as DetalleCandidato;
use App\Livewire\Dashboard\Panel;
use App\Livewire\Entrevistas\Listado as ListadoEntrevistas;
use App\Livewire\Postulaciones\Detalle as DetalleDePostulacion;
use App\Livewire\Seleccion\Central;
use App\Livewire\Vacantes\Listado as ListadoVacantes;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Vacantes\BuscadorDeVacantes;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;

/*
 * Matriz de acceso cruzado: una persona de la empresa Vela no puede ver ni tocar nada de Nimbus, ni por la
 * dirección directa ni manipulando identificadores en Livewire. La respuesta es 404 (no se revela que existe)
 * o 403, nunca el contenido.
 */

beforeEach(function () {
    Storage::fake('cvs');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->vela = Empresa::factory()->create(['nombre' => 'Vela SAC']);

    $this->vacante = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Vacante Secreta de Nimbus']);
    $this->postulacion = Postulacion::factory()->paraVacante($this->vacante)->create();
    $this->candidato = Candidato::query()->findOrFail($this->postulacion->candidato_id);
    $this->cv = Cv::factory()->procesado()->create(['candidato_id' => $this->candidato->id]);
    $this->entrevista = Entrevista::factory()->create(['postulacion_id' => $this->postulacion->id]);

    $this->intruso = User::factory()->deEmpresa($this->vela)->reclutador()->create();
});

it('niega por dirección directa cada recurso de otra empresa', function (string $ruta, string $metodo) {
    $this->actingAs($this->intruso);

    $url = match ($ruta) {
        'vacantes.show' => route('vacantes.show', $this->vacante->id),
        'seleccion.show' => route('seleccion.show', $this->vacante->id),
        'kanban.show' => route('kanban.show', $this->vacante->id),
        'postulaciones.show' => route('postulaciones.show', $this->postulacion->id),
        'candidatos.show' => route('candidatos.show', $this->candidato->id),
        'entrevistas.calendario' => route('entrevistas.calendario', $this->entrevista->id),
        'cvs.descargar' => route('cvs.descargar', $this->cv->id),
    };

    $respuesta = $this->get($url);

    expect($respuesta->getStatusCode())->toBeIn([403, 404]);
    expect($respuesta->getContent())->not->toContain('Vacante Secreta de Nimbus');
})->with([
    'vacante' => ['vacantes.show', 'GET'],
    'selección' => ['seleccion.show', 'GET'],
    'kanban (dirección anterior)' => ['kanban.show', 'GET'],
    'postulación' => ['postulaciones.show', 'GET'],
    'candidato' => ['candidatos.show', 'GET'],
    'calendario de la entrevista' => ['entrevistas.calendario', 'GET'],
    'descarga del CV' => ['cvs.descargar', 'GET'],
]);

it('la propia empresa sí accede a esos mismos recursos', function () {
    $propio = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->actingAs($propio);

    $this->get(route('vacantes.show', $this->vacante->id))->assertOk();
    $this->get(route('seleccion.show', $this->vacante->id))->assertOk();
    $this->get(route('postulaciones.show', $this->postulacion->id))->assertOk();
    $this->get(route('entrevistas.calendario', $this->entrevista->id))->assertOk();
});

it('niega a un postulante todas las pantallas del personal y descargar el CV de otra persona', function () {
    $postulante = User::factory()->postulante()->create();
    Candidato::factory()->create(['user_id' => $postulante->id]);
    $this->actingAs($postulante);

    foreach (['vacantes.show' => $this->vacante->id, 'seleccion.show' => $this->vacante->id, 'postulaciones.show' => $this->postulacion->id, 'candidatos.show' => $this->candidato->id] as $ruta => $id) {
        $this->get(route($ruta, $id))->assertForbidden();
    }

    expect($this->get(route('cvs.descargar', $this->cv->id))->getStatusCode())->toBeIn([403, 404]);
});

/** Livewire convierte un abort() o una autorización denegada en un estado de respuesta: debe ser 403 o 404. */
function negadoPorLivewire(Testable $componente): void
{
    foreach ([403, 404] as $codigo) {
        try {
            $componente->assertStatus($codigo);

            return;
        } catch (Throwable) {
            // probar con el otro código
        }
    }

    throw new AssertionFailedError('Livewire no negó el acceso (esperaba 403 o 404).');
}

it('no deja abrir en Livewire una vacante, entrevista, candidato o postulación de otra empresa', function () {
    actuarComo($this->intruso);

    negadoPorLivewire(Livewire::test(Panel::class)->call('verVistaPrevia', $this->vacante->id));
    negadoPorLivewire(Livewire::test(ListadoVacantes::class)->call('verVistaPrevia', $this->vacante->id));
    negadoPorLivewire(Livewire::test(ListadoEntrevistas::class)->call('confirmar', $this->entrevista->id));
    negadoPorLivewire(Livewire::test(DetalleDePostulacion::class, ['postulacion' => $this->postulacion]));
    negadoPorLivewire(Livewire::test(DetalleCandidato::class, ['candidato' => $this->candidato]));
    negadoPorLivewire(Livewire::test(Central::class, ['vacante' => $this->vacante]));

    // La entrevista sigue intacta.
    expect($this->entrevista->fresh()->estado)->toBe($this->entrevista->estado);
});

it('los buscadores no devuelven datos de otra empresa', function () {
    actuarComo($this->intruso);

    $encontradas = app(BuscadorDeVacantes::class)->buscar('Secreta');

    expect($encontradas)->toBe([]);
});

it('el administrador de la plataforma sí puede ver el recurso de cualquier empresa', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('vacantes.show', $this->vacante->id))->assertOk();
    $this->get(route('postulaciones.show', $this->postulacion->id))->assertOk();
});
