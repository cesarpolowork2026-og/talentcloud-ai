<?php

use App\Enums\EstadoEntrevista;
use App\Livewire\Entrevistas\Listado;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Entrevistas\ResumenDeAgenda;
use App\Support\Calendario\EventoIcs;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);
    $this->elena = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id]);

    $this->entrevista = Entrevista::factory()->create([
        'postulacion_id' => $this->elena->id,
        'entrevistador_id' => $this->marta->id,
        'inicia_at' => '2026-10-14 10:00',
        'duracion_min' => 45,
        'enlace' => 'https://meet.example.com/abc',
        'estado' => EstadoEntrevista::Confirmada,
    ]);

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

it('descarga la entrevista como evento de calendario con la hora en UTC', function () {
    $respuesta = $this->get(route('entrevistas.calendario', $this->entrevista))->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('entrevista-elena-ruiz-2026-10-14.ics');

    $ics = $respuesta->getContent();

    // 10:00 en Lima (UTC-5) son las 15:00 UTC; dura 45 minutos.
    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($ics)->toContain("DTSTART:20261014T150000Z\r\n")
        ->and($ics)->toContain("DTEND:20261014T154500Z\r\n")
        ->and($ics)->toContain("STATUS:CONFIRMED\r\n")
        ->and($ics)->toContain('UID:entrevista-'.$this->entrevista->id.'@talentcloud.ai')
        ->and($ics)->toContain('meet.example.com');
});

it('escapa y pliega los textos escritos por personas: no pueden abrir una línea nueva', function () {
    $this->elena->candidato->update(['nombre' => "Elena; Ruiz, \nEND:VEVENT\nBEGIN:VEVENT"]);
    $this->entrevista->update(['notas' => str_repeat('Conversación larga con acentos ñ. ', 10)]);

    $ics = EventoIcs::de($this->entrevista->fresh(['postulacion.candidato', 'postulacion.vacante.empresa', 'entrevistador']));

    expect(substr_count($ics, "BEGIN:VEVENT\r\n"))->toBe(1)
        ->and(substr_count($ics, "END:VEVENT\r\n"))->toBe(1)
        ->and($ics)->toContain('Elena\\; Ruiz\\, \\nEND:VEVENT\\nBEGIN:VEVENT');

    foreach (explode("\r\n", rtrim($ics)) as $linea) {
        expect(strlen($linea))->toBeLessThanOrEqual(75);
    }
});

it('una entrevista cancelada o pendiente se marca como tal en el calendario', function () {
    $this->entrevista->update(['estado' => EstadoEntrevista::Pendiente]);
    expect(EventoIcs::de($this->entrevista->fresh(['postulacion.candidato', 'postulacion.vacante.empresa', 'entrevistador'])))->toContain('STATUS:TENTATIVE');

    $this->entrevista->update(['estado' => EstadoEntrevista::Cancelada]);
    expect(EventoIcs::de($this->entrevista->fresh(['postulacion.candidato', 'postulacion.vacante.empresa', 'entrevistador'])))->toContain('STATUS:CANCELLED');
});

it('no entrega el calendario de otra empresa ni a un postulante', function () {
    $ajena = app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $vacante = Vacante::factory()->deEmpresa($vela)->create();
        $post = Postulacion::factory()->paraVacante($vacante)->create();

        return Entrevista::factory()->create(['postulacion_id' => $post->id, 'entrevistador_id' => User::factory()->deEmpresa($vela)->reclutador()->create()->id]);
    });

    $this->get(route('entrevistas.calendario', $ajena))->assertNotFound();

    $this->actingAs(User::factory()->postulante()->create())
        ->get(route('entrevistas.calendario', $this->entrevista))
        ->assertForbidden();
});

it('muestra arriba de la agenda cuántas entrevistas hay hoy, próximas y por confirmar', function () {
    Entrevista::factory()->create(['postulacion_id' => $this->elena->id, 'entrevistador_id' => $this->marta->id, 'inicia_at' => '2026-10-13 15:00', 'estado' => EstadoEntrevista::Pendiente]);
    Entrevista::factory()->create(['postulacion_id' => $this->elena->id, 'entrevistador_id' => $this->marta->id, 'inicia_at' => '2026-10-13 11:00', 'estado' => EstadoEntrevista::Cancelada]);
    Entrevista::factory()->create(['postulacion_id' => $this->elena->id, 'entrevistador_id' => $this->marta->id, 'inicia_at' => '2026-12-01 11:00']);

    $resumen = app(ResumenDeAgenda::class)->calcular();

    expect($resumen->hoy)->toBe(1)          // la de las 15:00 (la cancelada de las 11:00 no cuenta)
        ->and($resumen->proximas)->toBe(2)   // la de mañana (confirmada) y la de hoy a las 15:00 (pendiente)
        ->and($resumen->sin_confirmar)->toBe(2); // la pendiente de hoy y la de diciembre

    Livewire::test(Listado::class)
        ->assertSee('Hoy')
        ->assertSee('Próximos 7 días')
        ->assertSee('Por confirmar');
});
