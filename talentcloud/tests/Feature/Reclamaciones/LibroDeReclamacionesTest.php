<?php

use App\Enums\EstadoReclamacion;
use App\Enums\TipoReclamacion;
use App\Livewire\Dashboard\Panel as Dashboard;
use App\Livewire\Reclamaciones\Formulario;
use App\Livewire\Reclamaciones\Listado;
use App\Models\Reclamacion;
use App\Models\User;
use App\Notifications\AvisoGeneral;
use App\Notifications\HojaDeReclamacion;
use App\Notifications\RespuestaAReclamacion;
use App\Services\Reclamaciones\PlazoDeRespuesta;
use App\Services\Reclamaciones\ResponderReclamacion;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
 * Libro de Reclamaciones virtual (Ley 29571): hoja pública con número
 * correlativo, copias por correo, plazo en días hábiles y respuesta del admin.
 */

/** Rellena la hoja con datos válidos; $cambios reemplaza campos del formulario. */
function llenarHojaDeReclamacion(array $cambios = [])
{
    $componente = Livewire::test(Formulario::class);

    foreach ([
        'tipo' => 'reclamo',
        'nombre' => 'Lucía Prat',
        'tipoDocumento' => 'dni',
        'numeroDocumento' => '45678912',
        'domicilio' => 'Av. Arequipa 1234, Lima',
        'telefono' => '987 654 321',
        'email' => 'lucia@correo.com',
        'servicio' => 'Plan Pro',
        'monto' => '349',
        'detalle' => 'Se me cobró el plan Pro dos veces en el mismo mes.',
        'pedido' => 'Que se anule el cobro duplicado.',
        'acepta' => true,
        ...$cambios,
    ] as $campo => $valor) {
        $componente->set('form.'.$campo, $valor);
    }

    return $componente;
}

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('libro-reclamaciones|127.0.0.1');
    RateLimiter::clear('libro-reclamaciones-correo|'.sha1('lucia@correo.com'));
    config(['talentcloud.soporte.correo' => 'talentcloudia@gmail.com']);
    $this->admin = User::factory()->admin()->create();
});

// — Presentar una hoja —

it('registra la hoja con número correlativo, fecha límite y copias por correo', function () {
    Carbon::setTestNow('2026-10-02 10:00');

    llenarHojaDeReclamacion()
        ->call('presentar')
        ->assertHasNoErrors()
        ->assertSee('Recibimos tu hoja LR-2026-000001')
        ->assertSee('26 de octubre de 2026');

    $hoja = Reclamacion::sole();
    expect($hoja->numero)->toBe(1)
        ->and($hoja->tipo)->toBe(TipoReclamacion::Reclamo)
        ->and($hoja->estado)->toBe(EstadoReclamacion::Pendiente)
        ->and($hoja->monto)->toBe('349.00')
        // 15 días hábiles desde el 2 de octubre, sin fines de semana ni el feriado del 8.
        ->and($hoja->fecha_limite->toDateString())->toBe('2026-10-26');

    $a = fn (string $correo) => fn ($aviso, array $canales, AnonymousNotifiable $destino) => $destino->routes['mail'] === $correo;
    Notification::assertSentOnDemand(HojaDeReclamacion::class, $a('talentcloudia@gmail.com'));
    Notification::assertSentOnDemand(HojaDeReclamacion::class, $a('lucia@correo.com'));
    Notification::assertSentTo($this->admin, AvisoGeneral::class, fn (AvisoGeneral $aviso) => str_contains($aviso->texto, 'LR-2026-000001'));

    Carbon::setTestNow();
});

it('numera las hojas de forma correlativa', function () {
    llenarHojaDeReclamacion()->call('presentar');
    llenarHojaDeReclamacion(['tipo' => 'queja', 'email' => 'otro@correo.com'])->call('presentar');

    expect(Reclamacion::orderBy('numero')->pluck('numero')->all())->toBe([1, 2])
        ->and(Reclamacion::where('numero', 2)->sole()->codigo())->toBe('LR-'.now()->year.'-000002');
});

it('exige los datos del padre, madre o apoderado solo si es menor de edad', function () {
    llenarHojaDeReclamacion(['esMenor' => true])
        ->call('presentar')
        ->assertHasErrors(['form.apoderadoNombre', 'form.apoderadoNumeroDocumento']);

    // Sin ser menor, lo que se haya escrito del apoderado no se guarda.
    llenarHojaDeReclamacion(['apoderadoNombre' => 'Alguien'])->call('presentar')->assertHasNoErrors();

    expect(Reclamacion::sole()->apoderado_nombre)->toBeNull();
});

it('valida el formato del documento y la aceptación', function () {
    llenarHojaDeReclamacion(['numeroDocumento' => '1234', 'acepta' => false])
        ->call('presentar')
        ->assertHasErrors(['form.numeroDocumento', 'form.acepta'])
        ->assertSee('El DNI debe tener 8 dígitos.');

    expect(Reclamacion::count())->toBe(0);
});

it('no registra lo que envía un bot (campo trampa)', function () {
    llenarHojaDeReclamacion()->set('web', 'https://spam.example')->call('presentar')->assertDontSee('Recibimos tu hoja');

    expect(Reclamacion::count())->toBe(0);
    Notification::assertNothingSent();
});

it('limita las hojas por conexión', function () {
    config(['talentcloud.reclamaciones.por_hora' => 2]);

    llenarHojaDeReclamacion()->call('presentar');
    llenarHojaDeReclamacion()->call('presentar');
    llenarHojaDeReclamacion()->call('presentar')->assertHasErrors('form.detalle');

    expect(Reclamacion::count())->toBe(2);
});

it('muestra la página pública con los datos del proveedor', function () {
    config(['talentcloud.legal.responsable' => 'Talent Cloud S.A.C.', 'talentcloud.legal.ruc' => '20123456789']);

    $this->get('/libro-de-reclamaciones')
        ->assertOk()
        ->assertSee('Libro de Reclamaciones')
        ->assertSee('Talent Cloud S.A.C.')
        ->assertSee('20123456789')
        ->assertSee('no impide acudir a otras vías de solución de controversias');
});

it('envía lo que escribe el consumidor como texto literal: sin enlaces disfrazados en el correo', function () {
    llenarHojaDeReclamacion(['detalle' => 'Revisen esto: [Recupera tu cuenta](https://phishing.test) **urgente**'])->call('presentar');

    $html = (string) HojaDeReclamacion::paraConsumidor(Reclamacion::sole())->toMail(new AnonymousNotifiable)->render();

    expect($html)->not->toContain('href="https://phishing.test"')
        ->and($html)->not->toContain('<strong>urgente</strong>')
        ->and($html)->toContain('Recupera tu cuenta');
});

it('limita las copias que recibe un mismo correo al día, aunque cambie la conexión', function () {
    config(['talentcloud.reclamaciones.por_correo_al_dia' => 2]);

    llenarHojaDeReclamacion()->call('presentar');
    RateLimiter::clear('libro-reclamaciones|127.0.0.1');
    llenarHojaDeReclamacion()->call('presentar');
    RateLimiter::clear('libro-reclamaciones|127.0.0.1');
    llenarHojaDeReclamacion()->call('presentar')->assertHasErrors('form.email');

    expect(Reclamacion::count())->toBe(2);
});

// — Plazo —

it('cuenta días hábiles sin fines de semana ni feriados, Semana Santa incluida', function () {
    $plazo = app(PlazoDeRespuesta::class);

    expect($plazo->esHabil(Carbon::parse('2026-04-02')))->toBeFalse() // Jueves Santo 2026
        ->and($plazo->esHabil(Carbon::parse('2026-04-03')))->toBeFalse() // Viernes Santo 2026
        ->and($plazo->esHabil(Carbon::parse('2026-07-28')))->toBeFalse()
        ->and($plazo->esHabil(Carbon::parse('2026-10-10')))->toBeFalse() // sábado
        ->and($plazo->esHabil(Carbon::parse('2026-10-09')))->toBeTrue()
        ->and($plazo->sumarDiasHabiles(Carbon::parse('2026-04-01'), 1)->toDateString())->toBe('2026-04-06');
});

// — Atención por el admin SaaS —

it('el admin ve la hoja y registra la respuesta, que se envía al consumidor una sola vez', function () {
    llenarHojaDeReclamacion()->call('presentar');
    $hoja = Reclamacion::sole();
    actuarComo($this->admin);

    Livewire::test(Listado::class)
        ->assertSee($hoja->codigo())
        ->call('verDetalle', $hoja->id)
        ->assertSee('Se me cobró el plan Pro dos veces')
        ->set('respuesta', 'corta')
        ->call('responder')
        ->assertHasErrors('respuesta')
        ->set('respuesta', 'Revisamos tu caso y anulamos el cobro duplicado; verás la devolución en 5 días.')
        ->call('responder')
        ->assertHasNoErrors()
        ->assertSee('Enviada al consumidor el');

    $hoja->refresh();
    expect($hoja->estado)->toBe(EstadoReclamacion::Respondida)
        ->and($hoja->respondida_por)->toBe($this->admin->id);
    Notification::assertSentOnDemand(RespuestaAReclamacion::class, fn ($aviso, array $canales, AnonymousNotifiable $destino) => $destino->routes['mail'] === 'lucia@correo.com');

    // Una segunda respuesta no se registra.
    expect(fn () => app(ResponderReclamacion::class)->ejecutar($hoja, 'Otra respuesta distinta y suficientemente larga.', $this->admin))
        ->toThrow(OperacionNoPermitida::class, 'ya fue respondida');
});

it('encuentra una hoja por su código, como llega en los correos', function () {
    llenarHojaDeReclamacion()->call('presentar');
    llenarHojaDeReclamacion(['nombre' => 'Marco Díaz', 'email' => 'marco@correo.com'])->call('presentar');
    $segunda = Reclamacion::where('numero', 2)->sole();
    actuarComo($this->admin);

    Livewire::test(Listado::class)
        ->set('busqueda', $segunda->codigo())
        ->assertSee('Marco Díaz')
        ->assertDontSee('Lucía Prat')
        ->set('busqueda', 'lucía')
        ->assertSee('Lucía Prat')
        ->assertDontSee('Marco Díaz');
});

it('abre la hoja desde el enlace del correo a soporte', function () {
    llenarHojaDeReclamacion()->call('presentar');
    $hoja = Reclamacion::sole();

    $this->actingAs($this->admin)
        ->get(route('reclamaciones.index', ['ver' => $hoja->id]))
        ->assertOk()
        ->assertSee('Hoja '.$hoja->codigo());
});

it('solo el admin SaaS atiende las reclamaciones', function (string $rol) {
    $usuario = User::factory()->{$rol}()->create();

    $this->actingAs($usuario)->get('/reclamaciones')->assertForbidden();
})->with(['reclutador', 'postulante']);

it('muestra en el panel del admin las hojas pendientes y vencidas', function () {
    llenarHojaDeReclamacion()->call('presentar');
    Reclamacion::sole()->forceFill(['fecha_limite' => today()->subDay()])->save();
    actuarComo($this->admin);

    Livewire::test(Dashboard::class)
        ->assertSee('Libro de Reclamaciones')
        ->assertSee('1 hoja pendiente')
        ->assertSee('1 vencida');
});

// — Pie —

it('pone el correo de soporte y el Libro de Reclamaciones en el pie de las páginas públicas', function (string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertSee('mailto:talentcloudia@gmail.com', escape: false)
        ->assertSee('href="'.route('reclamaciones.crear').'"', escape: false);
})->with(['/', '/empleos', '/planes', '/privacidad', '/register']);
