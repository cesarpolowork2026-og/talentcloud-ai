<?php

use App\DTO\ReprogramacionData;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\ModalidadEntrevista;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Postulaciones\Notas;
use App\Livewire\Postulante\MisPostulaciones;
use App\Livewire\Seleccion\Central;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoEntrevistaReprogramada;
use App\Services\Cuenta\EliminarCuentaDePostulante;
use App\Services\Entrevistas\ReprogramarEntrevista;
use App\Services\Postulaciones\NotasDePostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Paquete 3 (ampliación 2): trabajo diario del reclutador. Notas internas por
 * postulación, búsqueda de candidatos por competencia y reprogramar entrevistas.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
        $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Diego Ferro']);
        $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create();

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        $this->marcoUsuario = User::factory()->postulante()->create();
        $this->marco = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marcoUsuario->id]);
        $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->marco->id]);
    });

    actuarComo($this->marta);
});

// — Notas internas —

it('añade una nota y la anota en la actividad', function () {
    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])
        ->set('texto', 'Buena comunicación, pedir referencias.')
        ->call('agregar')
        ->assertHasNoErrors()
        ->assertSet('texto', '')
        ->assertSee('Buena comunicación, pedir referencias.')
        ->assertSee('Marta Solís');

    $nota = Nota::sole();
    expect($nota->empresa_id)->toBe($this->nimbus->id)
        ->and($nota->autor_id)->toBe($this->marta->id)
        ->and(Actividad::latest('id')->value('descripcion'))->toBe('Marta Solís dejó una nota sobre Marco Díaz.');
});

it('no guarda notas vacías ni demasiado largas', function (string $texto) {
    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])
        ->set('texto', $texto)
        ->call('agregar')
        ->assertHasErrors('texto');

    expect(Nota::count())->toBe(0);
})->with([
    'vacía' => ['   '],
    'demasiado larga' => [str_repeat('a', NotasDePostulacion::MAXIMO_CARACTERES + 1)],
]);

it('solo el autor borra su nota', function () {
    $nota = app(NotasDePostulacion::class)->agregar($this->postulacion, $this->diego, 'Nota de Diego');

    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])
        ->assertDontSeeHtml('wire:click="borrar('.$nota->id.')"')
        ->call('borrar', $nota->id)
        ->assertForbidden();

    expect(fn () => app(NotasDePostulacion::class)->borrar($nota, $this->marta))->toThrow(OperacionNoPermitida::class);

    actuarComo($this->diego);
    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])->call('borrar', $nota->id);
    expect(Nota::count())->toBe(0);
});

it('las notas no las ve otra empresa ni el postulante', function () {
    app(NotasDePostulacion::class)->agregar($this->postulacion, $this->marta, 'Dato interno de Nimbus');

    actuarComo($this->clara);
    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])->assertNotFound();

    // El postulante no tiene contexto de empresa: el filtro ni siquiera le deja encontrar la postulación.
    actuarComo($this->marcoUsuario);
    Livewire::test(Notas::class, ['postulacionId' => $this->postulacion->id])->assertNotFound();
    Livewire::test(MisPostulaciones::class)->assertDontSee('Dato interno de Nimbus');
});

it('las notas se ven en el matching, en la ficha del candidato y se cuentan en el Kanban', function () {
    app(NotasDePostulacion::class)->agregar($this->postulacion, $this->marta, 'Revisar Kubernetes');

    $this->get(route('matching.show', $this->postulacion))->assertOk()->assertSee(['Notas del equipo', 'Revisar Kubernetes']);
    $this->get(route('candidatos.show', $this->marco))->assertOk()->assertSee(['Notas del equipo', 'Desarrollador Backend', 'Revisar Kubernetes']);
    $this->get(route('kanban.show', $this->backend))->assertOk();

    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('abrir', $this->postulacion->id)
        ->assertSee('Notas del equipo (1)');
});

it('borra las notas al eliminar la cuenta del postulante', function () {
    Storage::fake('cvs');
    app(NotasDePostulacion::class)->agregar($this->postulacion, $this->marta, 'Nota');

    app(EliminarCuentaDePostulante::class)->ejecutar($this->marcoUsuario);

    expect(Nota::sinFiltroDeEmpresa()->count())->toBe(0);
});

// — Búsqueda por competencia —

it('busca candidatos por competencia, sin contar las manuales de otra empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz']);
        Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $elena->id]);
        $elena->todasLasCompetencias()->attach(Competencia::obtenerOCrear('Kubernetes')->id, ['fuente' => FuenteCompetencia::Extraccion->value]);

        // Marco tiene Kubernetes solo como anotación manual de Vela: para Nimbus no cuenta.
        $this->marco->todasLasCompetencias()->attach(Competencia::obtenerOCrear('Kubernetes')->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->vela->id]);
    });

    Livewire::test(Candidatos::class)
        ->set('busqueda', 'kubernetes')
        ->assertSeeHtml('>Elena Ruiz<')
        ->assertDontSeeHtml('>Marco Díaz<');
});

// — Reprogramar entrevista —

/** Entrevista de Marco con Marta, mañana a las 10:00. */
function entrevistaDeMarco(object $test, EstadoEntrevista $estado = EstadoEntrevista::Pendiente): Entrevista
{
    return app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'postulacion_id' => $test->postulacion->id,
        'entrevistador_id' => $test->marta->id,
        'inicia_at' => now()->addDay()->setTime(10, 0),
        'estado' => $estado,
    ]));
}

function nuevaFecha(string $cuando, int $minutos = 45): ReprogramacionData
{
    return new ReprogramacionData(CarbonImmutable::parse($cuando), $minutos, ModalidadEntrevista::Videollamada, 'https://meet.example/nuevo');
}

it('reprograma una entrevista confirmada: vuelve a pendiente y avisa una sola vez', function () {
    Notification::fake();
    $entrevista = entrevistaDeMarco($this, EstadoEntrevista::Confirmada);
    actuarComo($this->diego); // alguien distinto del entrevistador, para que Marta reciba el aviso

    app(ReprogramarEntrevista::class)->ejecutar($entrevista, nuevaFecha('+3 days 16:00'));

    $entrevista->refresh();
    expect($entrevista->estado)->toBe(EstadoEntrevista::Pendiente)
        ->and($entrevista->inicia_at->format('H:i'))->toBe('16:00')
        ->and($entrevista->enlace)->toBe('https://meet.example/nuevo');

    Notification::assertSentToTimes($this->marta, AvisoEntrevistaReprogramada::class, 1);
    Notification::assertSentToTimes($this->marcoUsuario, AvisoEntrevistaReprogramada::class, 1);
    Notification::assertNotSentTo($this->marcoUsuario, AvisoDeEntrevista::class); // ni "cancelada" ni "programada"
    expect(Actividad::latest('id')->value('descripcion'))->toStartWith('Se reprogramó la entrevista de Marco Díaz al');
});

it('no choca consigo misma al moverla unos minutos', function () {
    $entrevista = entrevistaDeMarco($this);

    app(ReprogramarEntrevista::class)->ejecutar($entrevista, nuevaFecha($entrevista->inicia_at->copy()->addMinutes(15)->toDateTimeString()));

    expect($entrevista->refresh()->inicia_at->format('H:i'))->toBe('10:15');
});

it('aplica las reglas de agenda al reprogramar', function (string $caso, string $mensaje) {
    $entrevista = entrevistaDeMarco($this, $caso === 'completada' ? EstadoEntrevista::Completada : EstadoEntrevista::Pendiente);
    $cuando = '+2 days 12:00';

    app(TenantContext::class)->ejecutarGlobal(function () use ($caso, &$cuando) {
        match ($caso) {
            'en el pasado' => $cuando = '-1 hour',
            'solapada' => Entrevista::factory()->create([
                'postulacion_id' => Postulacion::factory()->paraVacante($this->backend)->create()->id,
                'entrevistador_id' => $this->marta->id,
                'inicia_at' => now()->addDays(2)->setTime(12, 0),
            ]),
            'candidato descartado' => $this->postulacion->update(['etapa' => EtapaPostulacion::Descartado]),
            'vacante cerrada' => $this->backend->update(['estado' => EstadoVacante::Cerrada]),
            'entrevistador desactivado' => $this->marta->forceFill(['desactivado_at' => now()])->save(),
            default => null,
        };
    });

    expect(fn () => app(ReprogramarEntrevista::class)->ejecutar($entrevista, nuevaFecha($cuando)))
        ->toThrow(OperacionNoPermitida::class, $mensaje);
})->with([
    'en el pasado' => ['en el pasado', 'fecha y hora futuras'],
    'completada' => ['completada', 'no se puede reprogramar'],
    'solapada' => ['solapada', 'ya tiene una entrevista en ese horario'],
    'candidato descartado' => ['candidato descartado', 'candidato descartado'],
    'vacante cerrada' => ['vacante cerrada', 'no está abierta'],
    'entrevistador desactivado' => ['entrevistador desactivado', 'usuario activo'],
]);

it('reprograma desde la pantalla de entrevistas', function () {
    Notification::fake();
    $entrevista = entrevistaDeMarco($this);
    $nueva = now()->addDays(4)->setTime(9, 30)->format('Y-m-d\TH:i');

    Livewire::test(Entrevistas::class)
        ->call('verDetalle', $entrevista->id)
        ->assertSee('Reprogramar')
        ->call('abrirReprogramacion', $entrevista->id)
        ->assertSet('mostrarReprogramacion', true)
        ->assertSet('reprogramacion.duracionMin', '45')
        ->set('reprogramacion.iniciaAt', $nueva)
        ->call('reprogramar')
        ->assertHasNoErrors()
        ->assertSet('mostrarReprogramacion', false);

    expect($entrevista->refresh()->inicia_at->format('Y-m-d\TH:i'))->toBe($nueva);
});

it('muestra el error de la fecha en su campo al reprogramar', function () {
    $entrevista = entrevistaDeMarco($this);

    Livewire::test(Entrevistas::class)
        ->call('abrirReprogramacion', $entrevista->id)
        ->set('reprogramacion.iniciaAt', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('reprogramar')
        ->assertHasErrors('reprogramacion.iniciaAt');
});

it('no deja reprogramar entrevistas de otra empresa', function () {
    $entrevista = entrevistaDeMarco($this);
    actuarComo($this->clara);

    Livewire::test(Entrevistas::class)->call('abrirReprogramacion', $entrevista->id)->assertNotFound();
});
