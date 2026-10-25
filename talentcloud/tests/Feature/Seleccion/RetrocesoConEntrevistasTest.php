<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EtapaPostulacion;
use App\Livewire\Postulaciones\Detalle;
use App\Livewire\Seleccion\Central;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\RetrocesoConEntrevistas;
use Livewire\Livewire;

/*
 * Devolver a «Postulado» o «En revisión» a quien ya pasó por las entrevistas y tiene una por realizar: se pregunta
 * si la entrevista se cancela o se mantiene. Sin entrevistas activas, o al ir hacia adelante, no se pregunta nada.
 */

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Analista de Datos']);
    $this->lucia = Postulacion::factory()->paraVacante($this->vacante)->create([
        'candidato_id' => Candidato::factory()->create(['nombre' => 'Lucía Paredes'])->id,
        'etapa' => EtapaPostulacion::Entrevista,
    ]);
    $this->entrevista = Entrevista::factory()->create([
        'postulacion_id' => $this->lucia->id, 'entrevistador_id' => $this->marta->id, 'estado' => EstadoEntrevista::Confirmada,
    ]);

    actuarComo($this->marta);
});

it('sin decidir, el servicio no mueve y pide la decisión', function () {
    expect(fn () => app(MoverPostulacion::class)->ejecutar($this->lucia, EtapaPostulacion::EnRevision, 0, $this->marta))
        ->toThrow(RetrocesoConEntrevistas::class, 'Lucía Paredes tiene 1 entrevista por realizar: indica si se cancela antes de moverla a «En revisión».');

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::Entrevista);
});

it('al arrastrar hacia atrás pregunta, y «Mover y cancelarla» mueve y cancela la entrevista', function () {
    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->call('mover', $this->lucia->id, 'en_revision', 0)
        ->assertSet('mostrarRetroceso', true)
        ->assertSee('¿Cancelar la entrevista?')
        ->assertSee('Lucía Paredes tiene 1 entrevista por realizar')
        ->assertHasNoErrors()
        ->call('confirmarRetroceso', true)
        ->assertSet('mostrarRetroceso', false)
        ->assertSet('retroceso', null);

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::EnRevision)
        ->and($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Cancelada);
});

it('«Mover y mantenerla» mueve sin tocar la entrevista', function () {
    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->call('mover', $this->lucia->id, 'postulado', 0)
        ->call('confirmarRetroceso', false);

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::Postulado)
        ->and($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Confirmada);
});

it('«No mover» deja todo como estaba', function () {
    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->call('mover', $this->lucia->id, 'en_revision', 0)
        ->set('mostrarRetroceso', false);

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::Entrevista)
        ->and($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Confirmada);
});

it('«Mover a…» del tablero y de la ficha también preguntan', function () {
    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->call('abrir', $this->lucia->id)
        ->set('etapaDestino', 'en_revision')
        ->call('moverA')
        ->assertSet('mostrarDetalle', false)
        ->assertSet('mostrarRetroceso', true);

    Livewire::test(Detalle::class, ['postulacion' => $this->lucia])
        ->set('etapaDestino', 'postulado')
        ->call('moverA')
        ->assertSet('mostrarRetroceso', true)
        ->assertNotDispatched('postulacion-movida')
        ->call('confirmarRetroceso', true)
        ->assertDispatched('postulacion-movida');

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::Postulado)
        ->and($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Cancelada);
});

it('no pregunta sin entrevistas por realizar ni al mover hacia adelante', function () {
    $this->entrevista->update(['estado' => EstadoEntrevista::Completada, 'inicia_at' => now()->subDay()]);

    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->call('mover', $this->lucia->id, 'oferta', 0)
        ->assertSet('mostrarRetroceso', false)
        ->call('mover', $this->lucia->id, 'en_revision', 0)
        ->assertSet('mostrarRetroceso', false)
        ->assertHasNoErrors();

    expect($this->lucia->fresh()->etapa)->toBe(EtapaPostulacion::EnRevision);
});
