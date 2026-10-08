<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\PostulacionCambioDeEtapa;
use App\Livewire\Kanban\Tablero;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);

    $crear = fn (string $nombre, EtapaPostulacion $etapa, int $orden, ?int $match = null) => Postulacion::factory()
        ->paraVacante($this->backend)
        ->create([
            'candidato_id' => Candidato::factory()->create(['nombre' => $nombre, 'titular' => 'Backend'])->id,
            'etapa' => $etapa,
            'orden' => $orden,
            'match_score' => $match,
        ]);

    $this->elena = $crear('Elena Ruiz', EtapaPostulacion::Postulado, 0, 88);
    $this->marco = $crear('Marco Díaz', EtapaPostulacion::Postulado, 1, 64);
    $this->sofia = $crear('Sofía Nieto', EtapaPostulacion::EnRevision, 0, 91);
    $this->pablo = $crear('Pablo Vega', EtapaPostulacion::Descartado, 0, 42);

    actuarComo($this->marta);
});

function columna(Vacante $vacante, EtapaPostulacion $etapa): array
{
    return Postulacion::where('vacante_id', $vacante->id)
        ->where('etapa', $etapa)
        ->orderBy('orden')
        ->with('candidato')
        ->get()
        ->map(fn (Postulacion $p) => $p->candidato->nombre.':'.$p->orden)
        ->all();
}

it('muestra el Kanban del prototipo', function () {
    $this->get(route('kanban.show', $this->backend))
        ->assertOk()
        ->assertSee('Kanban · Desarrollador Backend')
        ->assertSee('Nimbus Corp — 3 postulaciones activas.')
        ->assertSeeInOrder(['Postulado', 'En revisión', 'Entrevista', 'Oferta', 'Contratado', 'Descartado'])
        ->assertSee('88% match')
        ->assertSee('Descartar a Elena Ruiz', false);
});

it('abre desde el menú la vacante activa más reciente', function () {
    $this->get('/kanban')->assertOk()->assertSee('Kanban · Desarrollador Backend');
});

it('muestra un estado vacío si no hay vacantes activas', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    $this->get('/kanban')->assertOk()->assertSee('No hay vacantes abiertas para mostrar su Kanban.');
});

it('mueve entre columnas y renumera ambas sin huecos', function () {
    Event::fake([PostulacionCambioDeEtapa::class]);

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->call('mover', $this->elena->id, 'en_revision', 0)
        ->assertHasNoErrors();

    expect(columna($this->backend, EtapaPostulacion::Postulado))->toBe(['Marco Díaz:0'])
        ->and(columna($this->backend, EtapaPostulacion::EnRevision))->toBe(['Elena Ruiz:0', 'Sofía Nieto:1'])
        ->and($this->elena->fresh()->etapa_cambiada_at)->not->toBeNull();

    Event::assertDispatched(PostulacionCambioDeEtapa::class, fn ($e) => $e->origen === EtapaPostulacion::Postulado
        && $e->destino === EtapaPostulacion::EnRevision
        && $e->actor->is($this->marta));
});

it('reordena dentro de la misma columna sin disparar el evento', function () {
    Event::fake([PostulacionCambioDeEtapa::class]);

    app(MoverPostulacion::class)->ejecutar($this->marco, EtapaPostulacion::Postulado, 0, $this->marta);

    expect(columna($this->backend, EtapaPostulacion::Postulado))->toBe(['Marco Díaz:0', 'Elena Ruiz:1']);
    Event::assertNotDispatched(PostulacionCambioDeEtapa::class);
});

it('ajusta posiciones fuera de rango al final de la columna', function () {
    app(MoverPostulacion::class)->ejecutar($this->elena, EtapaPostulacion::EnRevision, 99, $this->marta);

    expect(columna($this->backend, EtapaPostulacion::EnRevision))->toBe(['Sofía Nieto:0', 'Elena Ruiz:1']);
});

it('descarta con la confirmación del prototipo, al final de Descartado', function () {
    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->call('confirmarDescarte', $this->elena->id)
        ->assertSet('mostrarDescarte', true)
        ->assertSee('Descartar a Elena Ruiz')
        ->assertSee('El candidato se moverá fuera del proceso de esta vacante. Esta acción no elimina su perfil ni su CV.')
        ->call('descartar');

    expect(columna($this->backend, EtapaPostulacion::Descartado))->toBe(['Pablo Vega:0', 'Elena Ruiz:1'])
        ->and(Candidato::where('nombre', 'Elena Ruiz')->exists())->toBeTrue();
});

it('mueve desde el diálogo con "Mover a…" (alternativa al arrastre)', function () {
    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->call('abrir', $this->marco->id)
        ->assertSee('Columna: Postulado.')
        ->assertSee('64% de coincidencia con la vacante.')
        ->set('etapaDestino', 'oferta')
        ->call('moverA')
        ->assertSet('mostrarDetalle', false);

    expect($this->marco->fresh()->etapa)->toBe(EtapaPostulacion::Oferta);
});

it('no permite mover en una vacante cerrada', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->assertSee('La vacante está cerrada: el Kanban es de solo lectura.')
        ->call('mover', $this->elena->id, 'oferta', 0)
        ->assertHasErrors(['tablero']);

    expect($this->elena->fresh()->etapa)->toBe(EtapaPostulacion::Postulado);
});

it('rechaza etapas inexistentes', function () {
    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->call('mover', $this->elena->id, 'aprobado-por-ia', 0)
        ->assertNotFound();
});

it('no deja mover postulaciones de otra vacante ni de otra empresa', function () {
    // Datos de otra empresa: desde el contexto de Nimbus no se pueden crear (lo impide BelongsToEmpresa).
    $ajena = app(TenantContext::class)->ejecutarGlobal(
        fn () => Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa(Empresa::factory()->create())->create())->create()
    );
    $otraPropia = Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($this->nimbus)->create())->create();

    Livewire::test(Tablero::class, ['vacante' => $this->backend])->call('mover', $ajena->id, 'oferta', 0)->assertNotFound();
    Livewire::test(Tablero::class, ['vacante' => $this->backend])->call('mover', $otraPropia->id, 'oferta', 0)->assertNotFound();

    $this->get(route('kanban.show', $ajena->vacante_id))->assertNotFound();
});

it('niega el Kanban al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/kanban')->assertForbidden();
});

it('bloquea movimientos en una vacante cerrada también desde el servicio', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    app(MoverPostulacion::class)->ejecutar($this->elena->fresh(), EtapaPostulacion::Oferta, 0, $this->marta);
})->throws(OperacionNoPermitida::class);

// — Descartar con entrevistas por realizar —

it('no descarta a quien tiene entrevistas por realizar y explica qué hacer', function () {
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'estado' => EstadoEntrevista::Confirmada]);

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->call('confirmarDescarte', $this->sofia->id)
        ->call('descartar')
        ->assertHasErrors('tablero')
        ->assertSee('Sofía Nieto tiene 1 entrevista por realizar. Cancélala en «Entrevistas» y vuelve a descartar.');

    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::EnRevision);
});

it('descarta cuando sus entrevistas ya están realizadas o canceladas', function () {
    foreach ([EstadoEntrevista::Completada, EstadoEntrevista::Cancelada] as $estado) {
        Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => now()->subDay(), 'estado' => $estado]);
    }

    app(MoverPostulacion::class)->descartar($this->sofia, $this->marta);

    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::Descartado);
});
