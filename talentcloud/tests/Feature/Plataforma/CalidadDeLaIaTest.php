<?php

use App\Enums\MotorMatching;
use App\Jobs\CalcularMatch;
use App\Livewire\Plataforma\CalidadIa;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Plataforma\CalidadDeLaIa;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Paquete 2 (ampliación 2): "Calidad de la IA" del admin SaaS. Cierra el ciclo
 * de la supervisión humana: las marcas de "resultado incorrecto" llegan a
 * alguien, y los análisis básicos se pueden rehacer con la IA.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);

        $backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);
        $this->java = Requisito::factory()->create(['vacante_id' => $backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id]);

        $detalle = ['requisitos' => [[
            'requisito_id' => $this->java->id, 'competencia' => 'Java', 'porcentaje' => 90,
            'nivel' => 'coincide', 'evidencia' => 'Cinco años con Java y Spring Boot.', 'motivo' => 'Coincidencia exacta',
        ]]];

        // Últimos 30 días: 2 con el servicio de IA y 1 con el análisis básico.
        $this->conIa = Postulacion::factory()->paraVacante($backend)->create(['match_motor' => MotorMatching::Http, 'match_calculado_at' => now()->subDays(2), 'match_detalle' => $detalle]);
        Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($this->vela)->create())->create(['match_motor' => MotorMatching::Http, 'match_calculado_at' => now()->subDay()]);
        $this->basica = Postulacion::factory()->paraVacante($backend)->create(['match_motor' => MotorMatching::Fake, 'match_calculado_at' => now()->subDay()]);
        // Fuera del periodo, pero sigue pendiente de rehacer con IA.
        $this->basicaAntigua = Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($this->vela)->create())
            ->create(['match_motor' => MotorMatching::Fake, 'match_calculado_at' => now()->subDays(40)]);

        $marca = new MatchFeedback(['postulacion_id' => $this->conIa->id, 'requisito_id' => $this->java->id, 'user_id' => $this->marta->id, 'comentario' => 'Solo lo usó en la universidad.']);
        $marca->empresa_id = $this->nimbus->id;
        $marca->save();

        Cv::factory()->conError()->create();
    });

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'ia',
        'payload' => json_encode(['displayName' => CalcularMatch::class, 'data' => ['command' => 'TEXTO-PRIVADO-DEL-CV']]),
        'exception' => 'RuntimeException: DETALLE-PRIVADO-DEL-ERROR',
        'failed_at' => now(),
    ]);

    $this->admin = User::factory()->admin()->create();
    actuarComo($this->admin);
});

it('muestra al admin las cifras de la plataforma', function () {
    $resumen = app(CalidadDeLaIa::class)->resumen();

    expect($resumen->calculadas)->toBe(3)
        ->and($resumen->conIa)->toBe(2)
        ->and($resumen->porcentajeConIa())->toBe(67)
        ->and($resumen->basicas)->toBe(1)
        ->and($resumen->basicasPendientes)->toBe(2)
        ->and($resumen->cvsConError)->toBe(1)
        ->and($resumen->trabajosFallidos)->toBe(1)
        ->and($resumen->marcasDelPeriodo)->toBe(1);

    $this->get('/calidad-ia')
        ->assertOk()
        ->assertSee(['Calidad de la IA', 'Analizadas con IA', '67%', 'Con análisis básico', 'Marcadas como incorrectas', 'CVs con error de lectura']);
});

it('muestra cada resultado marcado con lo que dijo la IA', function () {
    Livewire::test(CalidadIa::class)
        ->assertSee(['Desarrollador Backend', 'Nimbus Corp', 'Java', 'Coincide', 'Cinco años con Java y Spring Boot.', 'Marta Solís', 'Solo lo usó en la universidad.'])
        ->assertSeeHtml('href="'.route('matching.show', $this->conIa->id).'"');
});

it('muestra los trabajos fallidos sin sus datos ni el error', function () {
    Livewire::test(CalidadIa::class)
        ->assertSee('CalcularMatch')
        ->assertSee('1 en total')
        ->assertDontSee('TEXTO-PRIVADO-DEL-CV')
        ->assertDontSee('DETALLE-PRIVADO-DEL-ERROR');
});

it('rehace con la IA los análisis básicos de todas las empresas', function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.matching.motor' => 'http']);
    Queue::fake();

    Livewire::test(CalidadIa::class)
        ->call('confirmarRehacer')
        ->assertSet('mostrarConfirmacion', true)
        ->call('rehacerBasicos')
        ->assertHasNoErrors()
        ->assertSet('encoladas', 2)
        ->assertSee('Rehaciendo con IA…')
        ->assertSee('0 de 2');

    Queue::assertPushed(CalcularMatch::class, 2);
    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $this->basicaAntigua->id && $job->empresaId === $this->vela->id);
});

it('mientras hay trabajo en cola sondea la pantalla y no deja volver a encolar', function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.matching.motor' => 'http']);
    Queue::fake();

    Livewire::test(CalidadIa::class)
        ->call('rehacerBasicos')
        ->assertSeeHtml('wire:poll.3s.visible')
        ->assertDontSee('Entendido')
        ->call('rehacerBasicos')
        ->assertHasErrors('rehacer')
        ->assertSet('encoladas', 2);

    Queue::assertPushed(CalcularMatch::class, 2);
});

it('al terminar el trabajo avisa que quedó listo, deja de sondear y se puede cerrar', function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.matching.motor' => 'http']);
    Queue::fake();

    // Sin nada en cola, con un lote ya encolado: el worker terminó.
    Livewire::test(CalidadIa::class)
        ->set('encoladas', 2)
        ->assertSee('Listo.')
        ->assertSee('Se procesaron 2 postulaciones')
        ->assertDontSeeHtml('wire:poll')
        ->assertSee('Reintentar con IA')
        ->call('cerrarResultado')
        ->assertSet('encoladas', null)
        ->assertDontSee('Listo.');
});

it('al recargar la pantalla con el lote en marcha recupera su total y muestra el avance', function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.matching.motor' => 'http']);
    Queue::fake();

    Livewire::test(CalidadIa::class)->call('rehacerBasicos');

    // Otra carga de la pantalla (recarga o vuelta desde otra sección): sigue viendo «0 de 2», no solo «2 en cola».
    Livewire::test(CalidadIa::class)
        ->assertSet('encoladas', 2)
        ->assertSee('0 de 2')
        ->assertSeeHtml('role="progressbar"');
});

it('tras terminar el lote, unas tareas de IA ajenas no se muestran como su avance', function () {
    config(['talentcloud.ia.habilitada' => true, 'talentcloud.matching.motor' => 'http']);
    Queue::fake();

    $pantalla = Livewire::test(CalidadIa::class)->call('rehacerBasicos');

    // El worker terminó el lote: la pantalla lo ve y suelta el total recordado.
    Queue::fake();
    $pantalla->call('$refresh')->assertSee('Listo.');

    // Después entra una tarea ajena (p. ej. el análisis de un CV nuevo): otra carga ya no hereda el lote.
    dispatch(new CalcularMatch(1, 1));
    Livewire::test(CalidadIa::class)
        ->assertSet('encoladas', null)
        ->assertSee('Análisis de IA en curso…')
        ->assertDontSeeHtml('role="progressbar"');
});

it('no rehace nada si el servicio de IA no está habilitado', function () {
    config(['talentcloud.ia.habilitada' => false]);
    Queue::fake();

    Livewire::test(CalidadIa::class)
        ->call('rehacerBasicos')
        ->assertHasErrors('rehacer');

    Queue::assertNothingPushed();
});

it('sin cálculos en el periodo no inventa un porcentaje', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::query()->update(['match_calculado_at' => now()->subDays(60)]));

    expect(app(CalidadDeLaIa::class)->resumen()->porcentajeConIa())->toBeNull();
});

it('es solo del admin SaaS', function (string $rol) {
    $usuario = $rol === 'reclutador' ? $this->marta : User::factory()->{$rol}()->create();
    actuarComo($usuario);

    $this->get('/calidad-ia')->assertForbidden();
    Livewire::test(CalidadIa::class)->assertForbidden();
})->with(['empresa', 'reclutador', 'postulante']);
