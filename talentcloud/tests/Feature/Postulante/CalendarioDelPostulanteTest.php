<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Livewire\Postulante\MisPostulaciones;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * «Agregar al calendario» del postulante: solo sus entrevistas pendientes o confirmadas, con lo que ya ve
 * en «Mis postulaciones» (nunca las notas internas ni el entrevistador).
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

    $this->entrevista = entrevistaDeCalendario($this->postulacion, $this->marta, [
        'inicia_at' => '2026-10-14 10:00',
        'duracion_min' => 45,
        'enlace' => 'https://meet.example.com/abc',
        'notas' => 'Preguntar por su salida del último empleo.',
        'estado' => EstadoEntrevista::Confirmada,
    ]);

    actuarComo($this->marco);
});

afterEach(fn () => Carbon::setTestNow());

/** @param  array<string, mixed>  $datos */
function entrevistaDeCalendario(Postulacion $postulacion, User $entrevistador, array $datos = []): Entrevista
{
    return app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create(array_merge([
        'empresa_id' => $postulacion->empresa_id,
        'postulacion_id' => $postulacion->id,
        'entrevistador_id' => $entrevistador->id,
    ], $datos)));
}

it('descarga el evento con la vacante y la empresa, sin notas ni entrevistador', function () {
    $respuesta = $this->get(route('postulante.entrevistas.calendario', $this->entrevista->id))->assertOk();

    $ics = $respuesta->getContent();

    expect($respuesta->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('entrevista-2026-10-14.ics')
        ->and($ics)->toContain("DTSTART:20261014T150000Z\r\n")
        ->and($ics)->toContain("DTEND:20261014T154500Z\r\n")
        ->and($ics)->toContain('SUMMARY:Entrevista: Desarrollador Backend · Nimbus Corp')
        ->and($ics)->toContain('meet.example.com')
        ->and($ics)->not->toContain('Preguntar')
        ->and($ics)->not->toContain('Marta')
        ->and($ics)->not->toContain('Entrevistador');
});

it('no entrega la entrevista de otra persona ni una cancelada o ya realizada', function () {
    $otra = User::factory()->postulante()->create();
    $candidatoAjeno = Candidato::factory()->create(['user_id' => $otra->id]);
    $ajena = app(TenantContext::class)->ejecutarGlobal(
        fn () => Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $candidatoAjeno->id]),
    );
    $deLaOtra = entrevistaDeCalendario($ajena, $this->marta, ['inicia_at' => '2026-10-15 10:00']);

    $this->get(route('postulante.entrevistas.calendario', $deLaOtra->id))->assertNotFound();

    foreach ([EstadoEntrevista::Cancelada, EstadoEntrevista::Completada] as $estado) {
        $entrevista = entrevistaDeCalendario($this->postulacion, $this->marta, ['inicia_at' => '2026-10-16 10:00', 'estado' => $estado]);
        $this->get(route('postulante.entrevistas.calendario', $entrevista->id))->assertNotFound();
    }

    $this->get(route('postulante.entrevistas.calendario', 999999))->assertNotFound();
});

it('el personal de la empresa no usa esta dirección: tiene la suya', function () {
    actuarComo($this->marta);

    $this->get(route('postulante.entrevistas.calendario', $this->entrevista->id))->assertForbidden();
    $this->get(route('entrevistas.calendario', $this->entrevista))->assertOk();
});

it('un postulante sin perfil de candidato recibe 404, no un error', function () {
    actuarComo(User::factory()->postulante()->create());

    $this->get(route('postulante.entrevistas.calendario', $this->entrevista->id))->assertNotFound();
});

it('ofrece «Agregar al calendario» solo en las entrevistas que aún se pueden asistir', function () {
    entrevistaDeCalendario($this->postulacion, $this->marta, ['inicia_at' => '2026-10-17 10:00', 'estado' => EstadoEntrevista::Cancelada]);
    entrevistaDeCalendario($this->postulacion, $this->marta, ['inicia_at' => '2026-10-10 10:00', 'estado' => EstadoEntrevista::Completada]);

    $html = Livewire::test(MisPostulaciones::class)->call('verDetalle', $this->postulacion->id)->html();

    expect($html)->toContain(route('postulante.entrevistas.calendario', $this->entrevista->id))
        ->and(substr_count($html, 'Agregar al calendario'))->toBeGreaterThanOrEqual(1)
        ->and($html)->not->toContain('entrevistas/'.($this->entrevista->id + 1).'/calendario')
        ->and($html)->not->toContain('entrevistas/'.($this->entrevista->id + 2).'/calendario');
});
