<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\ModalidadEntrevista;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Livewire\Entrevistas\Listado;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Diego Ferro']);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    $this->elena = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id]);
    $this->sofia = Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Sofía Nieto'])->id]);

    $this->entrevista = Entrevista::factory()->create([
        'postulacion_id' => $this->elena->id,
        'entrevistador_id' => $this->marta->id,
        'inicia_at' => '2026-09-25 10:00',
    ]);

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

/** Datos válidos del formulario, con lo que cada test cambie. */
function programarEntrevista(array $cambios = []): Testable
{
    $test = Livewire::test(Listado::class)->call('abrirFormulario');

    foreach ([
        'postulacionId' => (string) test()->sofia->id,
        'entrevistadorId' => (string) test()->marta->id,
        'iniciaAt' => '2026-09-25 15:00',
        'duracionMin' => '45',
        'modalidad' => 'videollamada',
        'enlace' => 'https://meet.example.com/abc',
        ...$cambios,
    ] as $campo => $valor) {
        $test->set($campo, $campo === 'iniciaAt' ? str_replace(' ', 'T', $valor) : $valor);
    }

    return $test->call('programar');
}

// — Pantalla del prototipo —

it('muestra la agenda como en el prototipo', function () {
    $this->get('/entrevistas')
        ->assertOk()
        ->assertSee('Agenda de entrevistas programadas.')
        ->assertSee('Buscar candidato o vacante…')
        ->assertSeeInOrder(['Candidato', 'Vacante', 'Entrevistador', 'Fecha', 'Estado', 'Acciones'])
        ->assertSeeInOrder(['Elena Ruiz', 'Desarrollador Backend', 'Marta Solís', '25 sep · 10:00', 'Pendiente', 'Cancelar']);
});

it('busca por candidato o por vacante', function () {
    $otra = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Data Analyst']);
    Entrevista::factory()->create([
        'postulacion_id' => Postulacion::factory()->paraVacante($otra)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Lucía Prat'])->id])->id,
        'entrevistador_id' => $this->diego->id,
    ]);

    Livewire::test(Listado::class)->set('busqueda', 'lucía')
        ->assertSee('Lucía Prat')->assertDontSeeHtml('>Elena Ruiz</button>');
    // Sin distinguir tildes ni mayúsculas: se escribe como se escribe.
    Livewire::test(Listado::class)->set('busqueda', 'LUCIA')
        ->assertSee('Lucía Prat')->assertDontSeeHtml('>Elena Ruiz</button>');
    Livewire::test(Listado::class)->set('busqueda', 'backend')
        ->assertSeeHtml('>Elena Ruiz</button>')->assertDontSee('Lucía Prat');
});

it('muestra el detalle con los textos del prototipo', function () {
    Livewire::test(Listado::class)
        ->call('verDetalle', $this->entrevista->id)
        ->assertSet('mostrarDetalle', true)
        ->assertSee('Entrevista · Elena Ruiz')
        ->assertSee('Entrevistador: Marta Solís')
        ->assertSee('Fecha: 25 sep · 10:00 (45 min · Videollamada)')
        ->assertSee('Estado: Pendiente')
        ->assertSee('Confirmar')
        ->assertDontSee('Marcar como completada'); // aún no empieza
});

// — Programar —

it('programa una entrevista y, por defecto, mueve al candidato a la etapa Entrevista', function () {
    Event::fake([EntrevistaProgramada::class]);

    programarEntrevista()->assertHasNoErrors()->assertSet('mostrarFormulario', false);

    $entrevista = Entrevista::where('postulacion_id', $this->sofia->id)->firstOrFail();
    expect($entrevista->empresa_id)->toBe($this->nimbus->id)
        ->and($entrevista->inicia_at->format('Y-m-d H:i'))->toBe('2026-09-25 15:00')
        ->and($entrevista->estado)->toBe(EstadoEntrevista::Pendiente)
        ->and($entrevista->enlace)->toBe('https://meet.example.com/abc')
        // El Kanban refleja la entrevista, con su historial, como cualquier movimiento.
        ->and($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::Entrevista)
        ->and($this->sofia->etapas()->get()->last()->etapa)->toBe(EtapaPostulacion::Entrevista);
    Event::assertDispatched(EntrevistaProgramada::class, fn (EntrevistaProgramada $e) => $e->entrevista->is($entrevista));
});

it('no mueve al candidato si el reclutador desmarca la casilla', function () {
    programarEntrevista(['moverAEntrevista' => false])->assertHasNoErrors();

    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::Postulado);
});

it('ofrece mover a Entrevista solo si el candidato aún no llegó a esa etapa', function () {
    Livewire::test(Listado::class)->call('abrirFormulario')
        ->set('postulacionId', (string) $this->sofia->id)
        ->assertSee('Mover al candidato a la etapa «Entrevista» del Kanban');

    $this->sofia->update(['etapa' => EtapaPostulacion::Oferta]);

    Livewire::test(Listado::class)->call('abrirFormulario')
        ->set('postulacionId', (string) $this->sofia->id)
        ->assertDontSee('Mover al candidato a la etapa');

    // Aunque llegara marcada, el servicio nunca hace retroceder a quien ya pasó de la entrevista.
    programarEntrevista()->assertHasNoErrors();
    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::Oferta);
});

it('no guarda enlace si la entrevista no es por videollamada', function () {
    programarEntrevista(['modalidad' => ModalidadEntrevista::Presencial->value])->assertHasNoErrors();

    expect(Entrevista::where('postulacion_id', $this->sofia->id)->value('enlace'))->toBeNull();
});

it('aplica las reglas de la agenda', function (array $cambios, string $campo, string $mensaje) {
    programarEntrevista($cambios)->assertHasErrors([$campo])->assertSee($mensaje);

    expect(Entrevista::where('postulacion_id', $this->sofia->id)->exists())->toBeFalse();
})->with([
    'en el pasado' => [['iniciaAt' => '2026-09-23 10:00'], 'iniciaAt', 'La entrevista debe ser en una fecha y hora futuras.'],
    'se cruza con otra' => [['iniciaAt' => '2026-09-25 10:30'], 'iniciaAt', 'Marta Solís ya tiene una entrevista en ese horario.'],
    'duración inválida' => [['duracionMin' => '17'], 'duracionMin', 'duración'],
    'enlace inválido' => [['enlace' => 'no es un enlace'], 'enlace', 'enlace'],
]);

it('permite entrevistas seguidas y reutiliza el horario de una cancelada', function () {
    // Termina a las 10:45: empezar justo entonces no se cruza.
    programarEntrevista(['iniciaAt' => '2026-09-25 10:45'])->assertHasNoErrors();

    $this->entrevista->update(['estado' => EstadoEntrevista::Cancelada]);
    $lucia = Postulacion::factory()->paraVacante($this->backend)->create();
    programarEntrevista(['postulacionId' => (string) $lucia->id, 'iniciaAt' => '2026-09-25 10:00'])->assertHasNoErrors();
});

it('exige un entrevistador activo de la empresa de la vacante', function () {
    $ajeno = app(TenantContext::class)->ejecutarGlobal(fn () => User::factory()->deEmpresa(Empresa::factory()->create())->reclutador()->create());
    programarEntrevista(['entrevistadorId' => (string) $ajeno->id])->assertHasErrors(['entrevistadorId']);

    $this->diego->forceFill(['desactivado_at' => now()])->save();
    programarEntrevista(['entrevistadorId' => (string) $this->diego->id])->assertHasErrors(['entrevistadorId']);
});

it('no programa entrevistas a descartados ni en vacantes cerradas', function () {
    $this->sofia->update(['etapa' => EtapaPostulacion::Descartado]);
    programarEntrevista()->assertHasErrors(['postulacionId'])->assertSee('No se puede programar una entrevista a un candidato descartado.');

    $cerrada = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'QA']);
    $enCerrada = Postulacion::factory()->paraVacante($cerrada)->create();
    programarEntrevista(['postulacionId' => (string) $enCerrada->id])->assertHasErrors(['postulacionId'])->assertSee('La vacante QA no está abierta.');
});

it('abre el formulario con la postulación elegida desde el Kanban', function () {
    Livewire::withQueryParams(['programar' => $this->sofia->id])
        ->test(Listado::class)
        ->assertSet('mostrarFormulario', true)
        ->assertSet('postulacionId', (string) $this->sofia->id)
        ->assertSet('entrevistadorId', (string) $this->marta->id);
});

// — Confirmar, completar y cancelar —

it('confirma una entrevista pendiente, una sola vez', function () {
    Livewire::test(Listado::class)->call('confirmar', $this->entrevista->id)->assertHasNoErrors();
    expect($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Confirmada);

    Livewire::test(Listado::class)->call('confirmar', $this->entrevista->id)
        ->assertHasErrors(['entrevista'])->assertSee('La entrevista ya está confirmada.');
});

it('solo da por completada una entrevista que ya empezó', function () {
    Livewire::test(Listado::class)->call('completar', $this->entrevista->id)
        ->assertSee('Solo se puede dar por completada una entrevista que ya empezó.');

    Carbon::setTestNow('2026-09-25 11:00');
    Livewire::test(Listado::class)->call('completar', $this->entrevista->id)->assertHasNoErrors();
    expect($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Completada);
});

it('cancela con la confirmación del prototipo y avisa una sola vez', function () {
    Event::fake([EntrevistaCancelada::class]);

    Livewire::test(Listado::class)
        ->call('confirmarCancelacion', $this->entrevista->id)
        ->assertSet('mostrarCancelacion', true)
        ->assertSee('Cancelar entrevista de Elena Ruiz')
        ->assertSee('Se notificará al candidato y al entrevistador. Esta acción no se puede deshacer.')
        ->call('cancelar')
        ->assertSet('mostrarCancelacion', false);

    expect($this->entrevista->fresh()->estado)->toBe(EstadoEntrevista::Cancelada);

    Livewire::test(Listado::class)->call('confirmarCancelacion', $this->entrevista->id)->call('cancelar')
        ->assertHasErrors(['entrevista']);
    Event::assertDispatchedTimes(EntrevistaCancelada::class, 1);
});

it('no muestra acciones en las entrevistas canceladas o completadas', function (EstadoEntrevista $estado) {
    $this->entrevista->update(['estado' => $estado]);

    expect(Livewire::test(Listado::class)->html())
        ->not->toContain('confirmarCancelacion(')
        ->not->toContain('wire:click="confirmar(')
        ->not->toContain('wire:click="completar(');
})->with([EstadoEntrevista::Cancelada, EstadoEntrevista::Completada]);

it('muestra en cada fila solo las acciones posibles según el estado y la hora', function () {
    $id = $this->entrevista->id;

    // Pendiente y futura: confirmar o cancelar; aún no se puede completar.
    $this->entrevista->update(['estado' => EstadoEntrevista::Pendiente, 'inicia_at' => now()->addDay()]);
    expect(Livewire::test(Listado::class)->html())
        ->toContain("wire:click=\"confirmar({$id})\"")
        ->toContain("confirmarCancelacion({$id})")
        ->not->toContain("wire:click=\"completar({$id})\"");

    // Confirmada y ya empezada: completar o cancelar.
    $this->entrevista->update(['estado' => EstadoEntrevista::Confirmada, 'inicia_at' => now()->subHour()]);
    expect(Livewire::test(Listado::class)->html())
        ->toContain("wire:click=\"completar({$id})\"")
        ->toContain("confirmarCancelacion({$id})")
        ->not->toContain("wire:click=\"confirmar({$id})\"");
});

// — Aislamiento y permisos —

it('no muestra ni deja tocar entrevistas de otra empresa', function () {
    $ajena = app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create();
        $vacante = Vacante::factory()->deEmpresa($vela)->enEstado(EstadoVacante::Abierta)->create();

        return Entrevista::factory()->create([
            'postulacion_id' => Postulacion::factory()->paraVacante($vacante)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Iván Torres'])->id])->id,
        ]);
    });

    Livewire::test(Listado::class)->assertDontSee('Iván Torres');
    Livewire::test(Listado::class)->call('verDetalle', $ajena->id)->assertNotFound();
    Livewire::test(Listado::class)->call('confirmarCancelacion', $ajena->id)->assertNotFound();
    programarEntrevista(['postulacionId' => (string) $ajena->postulacion_id])->assertHasErrors(['postulacionId']);
});

it('muestra al admin SaaS las entrevistas de todas las empresas', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'postulacion_id' => Postulacion::factory()->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Iván Torres'])->id])->id,
    ]));
    actuarComo(User::factory()->admin()->create());

    Livewire::test(Listado::class)->assertSee('Iván Torres')->assertSee('Elena Ruiz');
});

it('no deja entrar al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/entrevistas')->assertForbidden();
});
