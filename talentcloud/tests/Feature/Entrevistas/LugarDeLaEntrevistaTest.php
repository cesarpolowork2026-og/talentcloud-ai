<?php

use App\DTO\DatosDeEntrevista;
use App\DTO\NuevaEntrevistaData;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\ModalidadEntrevista;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Postulante\MisPostulaciones;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoDeEntrevista;
use App\Services\Entrevistas\ContactoDeEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Calendario\EventoIcs;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Dónde se hace una entrevista que no es una videollamada: la dirección de una presencial, el teléfono o la indicación de
 * una telefónica. Quien va a una entrevista tiene que saber adónde; antes solo se guardaba un enlace.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    $this->marco = User::factory()->postulante()->create();
    $this->candidato = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marco->id, 'email' => $this->marco->email]);
    $this->postulacion = app(TenantContext::class)->ejecutarGlobal(
        fn () => Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->candidato->id]),
    );

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

/** @param  array<string, mixed>  $estado */
function entrevistaConLugar(array $estado = []): Entrevista
{
    return app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create(array_merge([
        'empresa_id' => test()->postulacion->empresa_id,
        'postulacion_id' => test()->postulacion->id,
        'entrevistador_id' => test()->marta->id,
        'inicia_at' => '2026-10-14 10:00',
        'estado' => EstadoEntrevista::Confirmada,
    ], $estado)));
}

// — La regla por modalidad —

it('cada modalidad lleva su dato de contacto y descarta el que no le corresponde', function () {
    expect(ContactoDeEntrevista::para(ModalidadEntrevista::Videollamada, 'https://meet.example.com/a', 'Av. Larco 1301'))
        ->toBe(['enlace' => 'https://meet.example.com/a', 'lugar' => null])
        ->and(ContactoDeEntrevista::para(ModalidadEntrevista::Presencial, 'https://meet.example.com/a', '  Av. Larco 1301  '))
        ->toBe(['enlace' => null, 'lugar' => 'Av. Larco 1301'])
        ->and(ContactoDeEntrevista::para(ModalidadEntrevista::Telefonica, 'https://meet.example.com/a', '+51 999 999 999'))
        ->toBe(['enlace' => null, 'lugar' => '+51 999 999 999'])
        // La telefónica puede ir sin dato: la empresa llama al teléfono de la ficha.
        ->and(ContactoDeEntrevista::para(ModalidadEntrevista::Telefonica, null, '   '))
        ->toBe(['enlace' => null, 'lugar' => null]);
});

it('una entrevista presencial exige la dirección', function () {
    expect(fn () => ContactoDeEntrevista::para(ModalidadEntrevista::Presencial, null, '  '))
        ->toThrow(OperacionNoPermitida::class, 'Indica la dirección donde será la entrevista presencial.');
});

it('el enum dice cómo se llama el dato en cada modalidad', function () {
    expect(ModalidadEntrevista::Presencial->etiquetaDelLugar())->toBe('Dirección')
        ->and(ModalidadEntrevista::Telefonica->etiquetaDelLugar())->toBe('Teléfono o indicación')
        ->and(ModalidadEntrevista::Videollamada->llevaLugar())->toBeFalse()
        ->and(ModalidadEntrevista::Presencial->exigeLugar())->toBeTrue()
        ->and(ModalidadEntrevista::Telefonica->exigeLugar())->toBeFalse();
});

// — Programar —

function nuevaEntrevista(ModalidadEntrevista $modalidad, ?string $enlace = null, ?string $lugar = null): NuevaEntrevistaData
{
    return new NuevaEntrevistaData(
        postulacionId: test()->postulacion->id,
        entrevistadorId: test()->marta->id,
        iniciaAt: CarbonImmutable::parse('2026-10-15 10:00'),
        duracionMin: 45,
        modalidad: $modalidad,
        enlace: $enlace,
        notas: null,
        lugar: $lugar,
    );
}

it('guarda la dirección de una entrevista presencial y no el enlace', function () {
    Notification::fake();

    $entrevista = app(ProgramarEntrevista::class)->ejecutar(nuevaEntrevista(ModalidadEntrevista::Presencial, 'https://meet.example.com/a', 'Av. Larco 1301, Miraflores'), $this->marta);

    expect($entrevista->lugar)->toBe('Av. Larco 1301, Miraflores')->and($entrevista->enlace)->toBeNull();
});

it('no programa una presencial sin dirección y lo dice en el campo', function () {
    expect(fn () => app(ProgramarEntrevista::class)->ejecutar(nuevaEntrevista(ModalidadEntrevista::Presencial), $this->marta))
        ->toThrow(OperacionNoPermitida::class, 'Indica la dirección');

    expect(Entrevista::query()->count())->toBe(0);

    Livewire::test(Entrevistas::class)
        ->call('abrirFormulario')
        ->set('postulacionId', (string) $this->postulacion->id)
        ->set('entrevistadorId', (string) $this->marta->id)
        ->set('iniciaAt', '2026-10-15T10:00')
        ->set('modalidad', 'presencial')
        ->assertSee('Dirección')
        ->call('programar')
        ->assertHasErrors('lugar')
        ->set('lugar', 'Av. Larco 1301, piso 4')
        ->call('programar')
        ->assertHasNoErrors();

    expect(Entrevista::query()->value('lugar'))->toBe('Av. Larco 1301, piso 4');
});

it('una telefónica puede ir sin dato y una videollamada ignora lo escrito en el lugar', function () {
    Notification::fake();

    $telefonica = app(ProgramarEntrevista::class)->ejecutar(nuevaEntrevista(ModalidadEntrevista::Telefonica), $this->marta);
    expect($telefonica->lugar)->toBeNull();

    $video = app(ProgramarEntrevista::class)->ejecutar(new NuevaEntrevistaData(
        postulacionId: $this->postulacion->id, entrevistadorId: $this->marta->id, iniciaAt: CarbonImmutable::parse('2026-10-16 10:00'),
        duracionMin: 45, modalidad: ModalidadEntrevista::Videollamada, enlace: 'https://meet.example.com/a', notas: null, lugar: 'Av. Larco 1301',
    ), $this->marta);
    expect($video->lugar)->toBeNull()->and($video->enlace)->toBe('https://meet.example.com/a');
});

// — Reprogramar —

it('al reprogramar de videollamada a presencial pide la dirección y descarta el enlace', function () {
    Notification::fake();
    $entrevista = entrevistaConLugar(['enlace' => 'https://meet.example.com/a']);

    Livewire::test(Entrevistas::class)
        ->call('abrirReprogramacion', $entrevista->id)
        ->set('reprogramacion.iniciaAt', '2026-10-15T11:00')
        ->set('reprogramacion.modalidad', 'presencial')
        ->call('reprogramar')
        ->assertHasErrors('reprogramacion.lugar')
        ->set('reprogramacion.lugar', 'Calle Los Pinos 250, San Isidro')
        ->call('reprogramar')
        ->assertHasNoErrors();

    $entrevista->refresh();
    expect($entrevista->modalidad)->toBe(ModalidadEntrevista::Presencial)
        ->and($entrevista->lugar)->toBe('Calle Los Pinos 250, San Isidro')
        ->and($entrevista->enlace)->toBeNull();
});

it('el detalle para el equipo muestra la dirección', function () {
    $entrevista = entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => 'Av. Larco 1301, Miraflores']);

    Livewire::test(Entrevistas::class)->call('verDetalle', $entrevista->id)->assertSee('Dirección:')->assertSee('Av. Larco 1301, Miraflores');
});

// — Avisos y calendario —

it('los avisos llevan la dirección y la tratan como texto, nunca como enlace', function () {
    $entrevista = entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => 'Av. Larco 1301 [pincha aquí](https://sitio-malo.example)']);

    $datos = DatosDeEntrevista::de($entrevista);
    expect($datos->lugar)->toBe('Dirección: Av. Larco 1301 [pincha aquí](https://sitio-malo.example)');

    $html = AvisoDeEntrevista::programadaParaCandidato($datos)->toMail(new AnonymousNotifiable)->render()->toHtml();

    expect($html)->toContain('Dirección: Av. Larco 1301')
        ->and($html)->not->toContain('href="https://sitio-malo.example');
});

it('el calendario usa la dirección como ubicación, para el equipo y para el postulante', function () {
    $entrevista = entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => 'Av. Larco 1301, Miraflores']);

    $paraCandidato = EventoIcs::paraCandidato($entrevista, 'Desarrollador Backend', 'Nimbus Corp');
    $paraEquipo = EventoIcs::de($entrevista->fresh(['postulacion.candidato', 'postulacion.vacante.empresa', 'entrevistador']));

    foreach ([$paraCandidato, $paraEquipo] as $ics) {
        expect($ics)->toContain('LOCATION:Av. Larco 1301\\, Miraflores')->and($ics)->toContain('Dirección: Av. Larco 1301\\, Miraflores');
    }

    // Sin dirección, como antes: el enlace o la modalidad.
    $video = entrevistaConLugar(['enlace' => 'https://meet.example.com/a', 'inicia_at' => '2026-10-17 10:00']);
    expect(EventoIcs::paraCandidato($video, 'X', 'Y'))->toContain('LOCATION:https://meet.example.com/a');
});

// — Lo que ve el postulante —

it('el postulante ve dónde es su entrevista presencial o a qué número lo llaman', function () {
    entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => 'Av. Larco 1301, Miraflores']);
    entrevistaConLugar(['modalidad' => ModalidadEntrevista::Telefonica, 'lugar' => 'Te llamaremos al +51 999 999 999', 'inicia_at' => '2026-10-15 09:00']);
    actuarComo($this->marco);

    Livewire::test(MisPostulaciones::class)
        ->assertSee('Dirección:')->assertSee('Av. Larco 1301, Miraflores')
        ->assertSee('Teléfono o indicación:')->assertSee('Te llamaremos al +51 999 999 999');
});

it('una entrevista anterior sin dato le dice al postulante que la empresa se lo confirmará', function () {
    entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => null]);
    entrevistaConLugar(['modalidad' => ModalidadEntrevista::Telefonica, 'lugar' => null, 'inicia_at' => '2026-10-15 09:00']);
    actuarComo($this->marco);

    Livewire::test(MisPostulaciones::class)
        ->assertSee('La empresa te confirmará la dirección.')
        ->assertSee('La empresa te llamará.');
});

it('una entrevista cancelada no muestra dónde era', function () {
    entrevistaConLugar(['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => 'Av. Larco 1301, Miraflores', 'estado' => EstadoEntrevista::Cancelada]);
    actuarComo($this->marco);

    Livewire::test(MisPostulaciones::class)->assertDontSee('Av. Larco 1301');
});
