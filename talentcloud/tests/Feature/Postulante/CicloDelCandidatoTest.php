<?php

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\PostulacionCambioDeEtapa;
use App\Jobs\AvisarEstadoDePostulacion;
use App\Livewire\Postulante\DetalleVacante;
use App\Livewire\Postulante\MisPostulaciones;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoEstadoDePostulacion;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\CambiarEstadoVacante;
use App\Support\EnlaceExterno;
use App\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Paquete 1 (ampliación 2): el candidato se entera de su proceso. Avisos de
 * estado diferidos y sin repeticiones, aviso de cierre, sus entrevistas en el
 * portal y "Sobre la empresa".
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp', 'sector' => 'Tecnología', 'sitio_web' => 'https://nimbus.example', 'descripcion' => 'Software para logística.']);
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

        $this->marcoUsuario = User::factory()->postulante()->create();
        $this->marco = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marcoUsuario->id]);
        $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->marco->id]);

        // Registrada por el equipo, sin cuenta de postulante: solo recibe correos.
        $this->elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz', 'email' => 'elena@correo.com']);
        $this->deElena = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->elena->id]);
    });
});

/** Deja la postulación en una etapa y ejecuta el aviso como lo haría la cola. */
function avisarTrasPasarA(Postulacion $postulacion, EtapaPostulacion $etapa): void
{
    app(TenantContext::class)->ejecutarGlobal(fn () => $postulacion->update(['etapa' => $etapa]));

    AvisarEstadoDePostulacion::dispatchSync($postulacion->empresa_id, $postulacion->id);
}

// — Aviso de cambio de estado —

it('programa el aviso con demora al mover la tarjeta', function () {
    Queue::fake();
    actuarComo($this->marta);

    app(MoverPostulacion::class)->ejecutar($this->postulacion, EtapaPostulacion::EnRevision, 0, $this->marta);

    Queue::assertPushed(AvisarEstadoDePostulacion::class, fn (AvisarEstadoDePostulacion $job) => $job->postulacionId === $this->postulacion->id
        && $job->empresaId === $this->nimbus->id
        && $job->delay->greaterThanOrEqualTo(now()->addMinutes(9)));
});

it('avisa al postulante del estado que ve, con un texto amable', function (EtapaPostulacion $etapa, EstadoDeMiPostulacion $estado, string $texto) {
    Notification::fake();

    avisarTrasPasarA($this->postulacion, $etapa);

    Notification::assertSentTo($this->marcoUsuario, AvisoEstadoDePostulacion::class,
        fn (AvisoEstadoDePostulacion $aviso) => $aviso->texto === $texto && $aviso->url === route('postulante.postulaciones'));
    expect($this->postulacion->refresh()->estado_avisado)->toBe($estado);
})->with([
    'en revisión' => [EtapaPostulacion::EnRevision, EstadoDeMiPostulacion::EnRevision, 'Nimbus Corp está revisando tu postulación a Desarrollador Backend.'],
    'contratado' => [EtapaPostulacion::Contratado, EstadoDeMiPostulacion::Contratado, '¡Felicitaciones! Nimbus Corp eligió tu candidatura para Desarrollador Backend. Pronto se pondrán en contacto contigo.'],
    'no seleccionado' => [EtapaPostulacion::Descartado, EstadoDeMiPostulacion::NoSeleccionado, 'Nimbus Corp decidió continuar con otros perfiles para Desarrollador Backend. Gracias por tu interés y mucho éxito.'],
]);

it('no avisa de las etapas que no se comunican', function (EtapaPostulacion $etapa) {
    Notification::fake();

    avisarTrasPasarA($this->postulacion, $etapa);

    Notification::assertNothingSent();
})->with([
    'postulado (por ejemplo, un arrastre deshecho a tiempo)' => [EtapaPostulacion::Postulado],
    'entrevista (tiene su propio aviso)' => [EtapaPostulacion::Entrevista],
    'oferta (se comunica en persona)' => [EtapaPostulacion::Oferta],
]);

it('no repite un aviso ya enviado aunque la tarjeta se mueva otra vez', function () {
    Notification::fake();

    avisarTrasPasarA($this->postulacion, EtapaPostulacion::EnRevision);
    avisarTrasPasarA($this->postulacion, EtapaPostulacion::Entrevista);
    avisarTrasPasarA($this->postulacion, EtapaPostulacion::EnRevision);

    Notification::assertSentToTimes($this->marcoUsuario, AvisoEstadoDePostulacion::class, 1);
});

it('avisa solo por correo, sin enlace, al candidato sin cuenta', function () {
    Notification::fake();

    avisarTrasPasarA($this->deElena, EtapaPostulacion::EnRevision);

    Notification::assertSentOnDemand(AvisoEstadoDePostulacion::class,
        fn (AvisoEstadoDePostulacion $aviso, array $canales, AnonymousNotifiable $destino) => $destino->routes['mail'] === 'elena@correo.com' && $aviso->url === null);
});

it('no programa aviso cuando el cambio lo hizo el propio postulante', function () {
    Queue::fake();

    PostulacionCambioDeEtapa::dispatch($this->postulacion, EtapaPostulacion::Postulado, EtapaPostulacion::Descartado, $this->marcoUsuario);

    Queue::assertNotPushed(AvisarEstadoDePostulacion::class);
});

// — Cierre del proceso —

it('al cerrar la vacante avisa solo a quienes seguían en el proceso', function () {
    Queue::fake();
    $contratada = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)->create(['etapa' => EtapaPostulacion::Contratado]));
    $descartada = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)->create(['etapa' => EtapaPostulacion::Descartado]));
    actuarComo($this->marta);

    app(CambiarEstadoVacante::class)->ejecutar($this->backend, EstadoVacante::Cerrada);

    $avisadas = Queue::pushed(AvisarEstadoDePostulacion::class)->pluck('postulacionId')->sort()->values()->all();
    expect($avisadas)->toBe(collect([$this->postulacion->id, $this->deElena->id])->sort()->values()->all())
        ->and($avisadas)->not->toContain($contratada->id)
        ->and($avisadas)->not->toContain($descartada->id);
});

it('comunica el cierre del proceso', function () {
    Notification::fake();
    app(TenantContext::class)->ejecutarGlobal(fn () => $this->backend->update(['estado' => EstadoVacante::Cerrada]));

    AvisarEstadoDePostulacion::dispatchSync($this->nimbus->id, $this->postulacion->id);

    Notification::assertSentTo($this->marcoUsuario, AvisoEstadoDePostulacion::class,
        fn (AvisoEstadoDePostulacion $aviso) => $aviso->texto === 'El proceso de Desarrollador Backend en Nimbus Corp se cerró. Gracias por postular.');
});

// — Entrevistas en el portal —

it('muestra sus próximas entrevistas sin las notas internas', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        Entrevista::factory()->create([
            'postulacion_id' => $this->postulacion->id,
            'inicia_at' => now()->addDays(2)->setTime(10, 0),
            'enlace' => 'https://meet.example/abc',
            'notas' => 'Pedir referencias del último empleo.',
        ]);
        // De otro candidato: no debe aparecer.
        Entrevista::factory()->create(['postulacion_id' => $this->deElena->id, 'inicia_at' => now()->addDays(3)->setTime(16, 30)]);
    });
    actuarComo($this->marcoUsuario);

    Livewire::test(MisPostulaciones::class)
        ->assertSee('Próximas entrevistas')
        ->assertSee('Desarrollador Backend · Nimbus Corp')
        ->assertSee('Videollamada · 45 min')
        ->assertSeeHtml('href="https://meet.example/abc"')
        ->assertDontSee('16:30')
        ->assertDontSee('Pedir referencias');
});

it('muestra en el detalle todas las entrevistas de esa postulación, incluidas las canceladas', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'postulacion_id' => $this->postulacion->id,
        'estado' => EstadoEntrevista::Cancelada,
        'enlace' => 'https://meet.example/cancelada',
    ]));
    actuarComo($this->marcoUsuario);

    Livewire::test(MisPostulaciones::class)
        ->assertDontSee('Próximas entrevistas')
        ->call('verDetalle', $this->postulacion->id)
        ->assertSee('Entrevistas')
        ->assertSee('Cancelada')
        ->assertDontSeeHtml('https://meet.example/cancelada'); // una cancelada no ofrece el enlace
});

it('no pinta como enlace una dirección que no es http(s)', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'postulacion_id' => $this->postulacion->id,
        'enlace' => 'javascript:alert(1)',
    ]));
    actuarComo($this->marcoUsuario);

    Livewire::test(MisPostulaciones::class)->assertDontSeeHtml('javascript:alert(1)');
});

it('lee las entrevistas del candidato sin las notas internas', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create(['postulacion_id' => $this->postulacion->id, 'notas' => 'Interno']));

    $entrevistas = app(EntrevistaRepository::class)->delCandidato($this->marco);

    expect($entrevistas)->toHaveCount(1)
        ->and($entrevistas->first()->getAttributes())->not->toHaveKey('notas');
});

// — Sobre la empresa —

it('muestra "Sobre la empresa" en el detalle de la vacante', function () {
    actuarComo($this->marcoUsuario);

    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->assertSee('Sobre Nimbus Corp')
        ->assertSee('Tecnología')
        ->assertSee('Software para logística.')
        ->assertSeeHtml('href="https://nimbus.example"');
});

it('solo acepta enlaces http(s)', function (?string $url, ?string $esperado) {
    expect(EnlaceExterno::seguro($url))->toBe($esperado);
})->with([
    'https' => ['https://nimbus.example', 'https://nimbus.example'],
    'http' => ['http://nimbus.example/empleo', 'http://nimbus.example/empleo'],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,hola', null],
    'sin esquema' => ['nimbus.example', null],
    'vacío' => [null, null],
]);

it('«Mis postulaciones» muestra el progreso de cada proceso en marcha y nada donde no se sabe hasta dónde llegó', function () {
    actuarComo($this->marcoUsuario);

    // Elena sigue siendo de otra persona; Marco tiene dos: una En revisión y otra descartada.
    $this->postulacion->etapa = EtapaPostulacion::EnRevision;
    $this->postulacion->save();
    $otra = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista de Datos']);
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($otra)->create(['candidato_id' => $this->marco->id, 'etapa' => EtapaPostulacion::Descartado]));

    $html = Livewire::test(MisPostulaciones::class)->html();

    // Una postulación con progreso (compacto y oculto a lectores de pantalla), una vez en la tabla y otra en la tarjeta del
    // móvil, y la descartada sin inventar un recorrido.
    expect(substr_count($html, 'class="viz progreso progreso-compacto'))->toBe(2)
        ->and($html)->toContain('En revisión')->toContain('No seleccionado');
});
