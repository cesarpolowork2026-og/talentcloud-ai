<?php

use App\DTO\EvaluacionData;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Events\EvaluacionAprobada;
use App\Livewire\Evaluaciones\Listado;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Diego Ferro']);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);

    $this->elena = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id]);
    $this->marco = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Marco Díaz'])->id]);
    // Se evalúa tras una entrevista realizada.
    foreach ([$this->elena, $this->marco] as $entrevistada) {
        Entrevista::factory()->create(['postulacion_id' => $entrevistada->id, 'inicia_at' => now()->subDay(), 'estado' => EstadoEntrevista::Completada]);
    }

    $this->evaluacion = Evaluacion::factory()->create([
        'postulacion_id' => $this->elena->id,
        'evaluador_id' => $this->marta->id,
        'criterios' => [
            ['nombre' => 'Conocimiento técnico', 'puntaje' => 9],
            ['nombre' => 'Comunicación', 'puntaje' => 8],
            ['nombre' => 'Resolución de problemas', 'puntaje' => 9],
            ['nombre' => 'Ajuste cultural', 'puntaje' => 8.4],
        ],
        'puntaje' => 8.6,
        'comentario' => 'Sólida en backend.',
        'estado' => EstadoEvaluacion::Aprobado,
    ]);

    actuarComo($this->marta);
});

/** Evalúa a Marco con puntajes válidos, salvo lo que cambie cada test. */
function evaluarAMarco(array $puntajes = [], string $estado = 'en_revision'): Testable
{
    return Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('postulacionId', (string) test()->marco->id)
        ->set('puntajes', [
            'conocimiento_tecnico' => '5',
            'comunicacion' => '6',
            'resolucion_de_problemas' => '5',
            'ajuste_cultural' => '5.6',
            ...$puntajes,
        ])
        ->set('comentario', 'Le falta experiencia con Docker.')
        ->set('estadoEvaluacion', $estado)
        ->call('guardar');
}

// — Pantalla del prototipo —

it('muestra los resultados como en el prototipo', function () {
    $this->get('/evaluaciones')
        ->assertOk()
        ->assertSee('Resultados de las evaluaciones registradas por los reclutadores.')
        ->assertSee('Buscar candidato o vacante…')
        ->assertSeeInOrder(['Candidato', 'Vacante', 'Evaluador', 'Puntaje', 'Estado'])
        ->assertSeeInOrder(['Elena Ruiz', 'Desarrollador Backend', 'Marta Solís', '8.6/10', 'Aprobado']);
});

it('muestra el detalle con los criterios y el comentario', function () {
    Livewire::test(Listado::class)
        ->call('verDetalle', $this->evaluacion->id)
        ->assertSee('Evaluación · Elena Ruiz')
        ->assertSee('Evaluador: Marta Solís')
        ->assertSee('Puntaje: 8.6/10')
        ->assertSee('Estado: Aprobado')
        ->assertSee('Resolución de problemas')
        ->assertSee('Sólida en backend.')
        ->assertSee('Editar');
});

it('busca por candidato o por vacante', function () {
    Livewire::test(Listado::class)->set('busqueda', 'elena')->assertSee('Elena Ruiz');
    Livewire::test(Listado::class)->set('busqueda', 'data analyst')->assertSee('No se encontraron evaluaciones.');
});

// — Registrar y editar —

it('registra una evaluación con el promedio calculado en el servidor', function () {
    evaluarAMarco()->assertHasNoErrors()->assertSet('mostrarFormulario', false);

    $evaluacion = Evaluacion::where('postulacion_id', $this->marco->id)->firstOrFail();
    expect((float) $evaluacion->puntaje)->toBe(5.4)
        ->and($evaluacion->evaluador_id)->toBe($this->marta->id)
        ->and($evaluacion->empresa_id)->toBe($this->nimbus->id)
        ->and($evaluacion->estado)->toBe(EstadoEvaluacion::EnRevision)
        ->and(array_column($evaluacion->criterios, 'nombre'))->toBe(['Conocimiento técnico', 'Comunicación', 'Resolución de problemas', 'Ajuste cultural']);
});

it('muestra el promedio en vivo mientras se puntúa', function () {
    Livewire::test(Listado::class)
        ->call('abrirFormulario')
        ->set('puntajes.conocimiento_tecnico', '9')
        ->set('puntajes.comunicacion', '8')
        ->assertSeeHtml('Promedio: <strong>8.5/10</strong>');
});

it('exige puntuar cada criterio entre 0 y 10', function (array $puntajes) {
    evaluarAMarco($puntajes)->assertHasErrors();

    expect(Evaluacion::where('postulacion_id', $this->marco->id)->exists())->toBeFalse();
})->with([
    'más de 10' => [['comunicacion' => '11']],
    'negativo' => [['ajuste_cultural' => '-1']],
    'sin puntaje' => [['resolucion_de_problemas' => '']],
]);

it('no deja evaluar dos veces la misma postulación', function () {
    Livewire::test(Listado::class)->call('abrirFormulario')
        ->assertDontSee('Elena Ruiz — Desarrollador Backend'); // ya la evaluó

    Livewire::test(Listado::class)->call('abrirFormulario')
        ->set('postulacionId', (string) $this->elena->id)
        ->set('puntajes', ['conocimiento_tecnico' => '5', 'comunicacion' => '5', 'resolucion_de_problemas' => '5', 'ajuste_cultural' => '5'])
        ->call('guardar')
        ->assertHasErrors(['postulacionId'])
        ->assertSee('Ya evaluaste a Elena Ruiz para Desarrollador Backend: edita tu evaluación.');
});

it('avisa cuando una evaluación se aprueba, una sola vez', function () {
    Event::fake([EvaluacionAprobada::class]);

    evaluarAMarco(estado: 'aprobado')->assertHasNoErrors();
    Event::assertDispatchedTimes(EvaluacionAprobada::class, 1);

    // Editar una ya aprobada no vuelve a avisar.
    $evaluacion = Evaluacion::where('postulacion_id', $this->marco->id)->firstOrFail();
    Livewire::test(Listado::class)->call('editar', $evaluacion->id)->set('comentario', 'Actualizado')->call('guardar')->assertHasNoErrors();
    Event::assertDispatchedTimes(EvaluacionAprobada::class, 1);
});

it('edita una evaluación conservando sus criterios', function () {
    Livewire::test(Listado::class)
        ->call('editar', $this->evaluacion->id)
        ->assertSet('puntajes.ajuste_cultural', '8.4')
        ->assertSet('estadoEvaluacion', 'aprobado')
        ->set('puntajes.comunicacion', '10')
        ->call('guardar')
        ->assertHasNoErrors();

    expect((float) $this->evaluacion->fresh()->puntaje)->toBe(9.1);
});

it('solo editan la evaluación su autor y la cuenta de empresa', function () {
    actuarComo($this->diego);
    Livewire::test(Listado::class)->call('verDetalle', $this->evaluacion->id)->assertDontSee('Editar');
    Livewire::test(Listado::class)->call('editar', $this->evaluacion->id)->assertForbidden();

    actuarComo(User::factory()->deEmpresa($this->nimbus)->create()->assignRole('empresa'));
    Livewire::test(Listado::class)->call('editar', $this->evaluacion->id)->assertOk()->assertSet('mostrarFormulario', true);
});

it('abre el formulario con la postulación elegida desde el Kanban', function () {
    Livewire::withQueryParams(['evaluar' => $this->marco->id])
        ->test(Listado::class)
        ->assertSet('mostrarFormulario', true)
        ->assertSet('postulacionId', (string) $this->marco->id);
});

// — Aislamiento y permisos —

it('no muestra ni deja evaluar postulaciones de otra empresa', function () {
    [$ajena, $postulacionAjena] = app(TenantContext::class)->ejecutarGlobal(function () {
        $postulacion = Postulacion::factory()->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Iván Torres'])->id]);

        return [Evaluacion::factory()->create(['postulacion_id' => $postulacion->id]), $postulacion];
    });

    Livewire::test(Listado::class)->assertDontSee('Iván Torres');
    Livewire::test(Listado::class)->call('verDetalle', $ajena->id)->assertNotFound();
    Livewire::test(Listado::class)->call('abrirFormulario')
        ->set('postulacionId', (string) $postulacionAjena->id)
        ->set('puntajes', ['conocimiento_tecnico' => '5', 'comunicacion' => '5', 'resolucion_de_problemas' => '5', 'ajuste_cultural' => '5'])
        ->call('guardar')
        ->assertHasErrors(['postulacionId']);
});

it('el admin SaaS ve todas las evaluaciones pero no evalúa', function () {
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Listado::class)->assertSee('Elena Ruiz')->assertDontSee('+ Nueva evaluación');
    Livewire::test(Listado::class)->call('abrirFormulario')->assertForbidden();
    Livewire::test(Listado::class)->call('editar', $this->evaluacion->id)->assertForbidden();
});

it('no deja entrar al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/evaluaciones')->assertForbidden();
});

// — Solo se evalúa tras una entrevista realizada —

it('ofrece para evaluar solo a quien tuvo una entrevista realizada y no se retiró', function () {
    $sinEntrevista = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Pablo Vega'])->id]);
    Entrevista::factory()->create(['postulacion_id' => $sinEntrevista->id, 'estado' => EstadoEntrevista::Pendiente]);
    $this->marco->forceFill(['retirada_at' => now()])->save();
    $lucia = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Lucía Prat'])->id]);
    Entrevista::factory()->create(['postulacion_id' => $lucia->id, 'inicia_at' => now()->subDay(), 'estado' => EstadoEntrevista::Completada]);

    $opciones = Livewire::test(Listado::class)->call('abrirFormulario')->viewData('postulaciones');

    // Elena ya la evaluó Marta; Pablo solo tiene una entrevista pendiente; Marco se retiró.
    expect(array_keys($opciones))->toBe([$lucia->id]);
});

it('no registra una evaluación sin entrevista realizada, ni de una postulación retirada', function () {
    $sinEntrevista = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Pablo Vega'])->id]);
    $datos = new EvaluacionData(['conocimiento_tecnico' => 7, 'comunicacion' => 7, 'resolucion_de_problemas' => 7, 'ajuste_cultural' => 7], null, EstadoEvaluacion::Pendiente);

    expect(fn () => app(GuardarEvaluacion::class)->registrar($sinEntrevista->id, $this->diego, $datos))
        ->toThrow(OperacionNoPermitida::class, 'Pablo Vega todavía no tiene una entrevista realizada para Desarrollador Backend.');

    $this->marco->forceFill(['retirada_at' => now()])->save();

    expect(fn () => app(GuardarEvaluacion::class)->registrar($this->marco->id, $this->diego, $datos))
        ->toThrow(OperacionNoPermitida::class, 'Marco Díaz retiró su postulación a Desarrollador Backend');
});

it('explica por qué no se puede evaluar al llegar desde el Kanban', function () {
    $sinEntrevista = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Pablo Vega'])->id]);

    Livewire::withQueryParams(['evaluar' => $sinEntrevista->id])
        ->test(Listado::class)
        ->assertSet('mostrarFormulario', true)
        ->assertSet('postulacionId', '')
        ->assertHasErrors('postulacionId')
        ->assertSee('Pablo Vega todavía no tiene una entrevista realizada');
});
