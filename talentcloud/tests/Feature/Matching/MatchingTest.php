<?php

use App\Enums\EstadoVacante;
use App\Enums\MotorMatching;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Jobs\ExtraerTextoCv;
use App\Livewire\Matching\Detalle;
use App\Matching\ExplicacionDelPuntaje;
use App\Matching\MatchResult;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Cvs\SubirCv;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\GestionarRequisitos;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('cvs');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    $requisito = fn (string $nombre, TipoRequisito $tipo) => Requisito::factory()->create([
        'vacante_id' => $this->backend->id,
        'competencia_id' => Competencia::obtenerOCrear($nombre)->id,
        'tipo' => $tipo,
    ]);
    $this->java = $requisito('Java', TipoRequisito::Obligatorio);
    $this->kubernetes = $requisito('Kubernetes', TipoRequisito::Deseable);
    $this->aws = $requisito('Certificación AWS', TipoRequisito::Obligatorio);

    $this->elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz']);
    $cv = Cv::factory()->procesado("5 años de experiencia con Java en proyectos de alto tráfico.\nKubernetes (interés en aprender).")
        ->create(['candidato_id' => $this->elena->id]);
    $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->elena->id, 'cv_id' => $cv->id]);

    CalcularMatch::dispatchSync($this->nimbus->id, $this->postulacion->id);

    actuarComo($this->marta);
});

it('guarda el resultado explicado de la postulación', function () {
    $postulacion = $this->postulacion->fresh();

    expect($postulacion->match_motor)->toBe(MotorMatching::Fake)
        ->and($postulacion->match_calculado_at)->not->toBeNull()
        ->and($postulacion->match_score)->toBeGreaterThan(0)
        ->and(collect($postulacion->match_detalle['requisitos'])->pluck('nivel')->all())->toBe(['coincide', 'revisar', 'no_encontrado']);
});

it('muestra la pantalla Matching inteligente del prototipo', function () {
    $porcentaje = $this->postulacion->fresh()->matchPorcentaje();

    $this->get('/matching/'.$this->postulacion->id)
        ->assertOk()
        ->assertSee('Matching inteligente')
        ->assertSee('Elena Ruiz — Desarrollador Backend @ Nimbus Corp · '.$porcentaje.'% de coincidencia global.')
        ->assertSeeInOrder(['Java', 'Coincide', 'Kubernetes', 'Revisar', 'Certificación AWS', 'No encontrado'])
        ->assertSee('Ver evidencia')
        ->assertSee('5 años de experiencia con Java')
        ->assertSee('Requisito obligatorio')
        ->assertSee('la decisión final permanece bajo control humano');
});

it('/matching sin postulación lleva al ranking de la selección y con ella abre su detalle', function () {
    $this->get('/matching')->assertRedirect('/seleccion?vista=ranking');
    $this->get('/matching/'.$this->postulacion->id)->assertOk()->assertSee('Elena Ruiz — Desarrollador Backend');
});

it('marca y desmarca un resultado como incorrecto sin cambiar el matching', function () {
    $antes = $this->postulacion->fresh()->match_score;

    Livewire::test(Detalle::class, ['postulacion' => $this->postulacion])
        ->call('marcarIncorrecto', $this->kubernetes->id, true)
        // Doble envío: la marca queda puesta, no se quita.
        ->call('marcarIncorrecto', $this->kubernetes->id, true)
        ->assertSee('Marcaste este resultado como incorrecto.')
        ->assertSee('Quitar marca de incorrecto');

    expect(MatchFeedback::where('postulacion_id', $this->postulacion->id)->where('user_id', $this->marta->id)->pluck('requisito_id')->all())
        ->toBe([$this->kubernetes->id])
        ->and($this->postulacion->fresh()->match_score)->toBe($antes);

    Livewire::test(Detalle::class, ['postulacion' => $this->postulacion])
        ->call('marcarIncorrecto', $this->kubernetes->id, false)
        ->assertDontSee('Marcaste este resultado como incorrecto.');

    expect(MatchFeedback::count())->toBe(0);
});

it('no marca requisitos de otra vacante', function () {
    $otro = Requisito::factory()->create(['vacante_id' => Vacante::factory()->deEmpresa($this->nimbus)->create()->id]);

    Livewire::test(Detalle::class, ['postulacion' => $this->postulacion])
        ->call('marcarIncorrecto', $otro->id, true)
        ->assertNotFound();
});

it('recalcula al cambiar los requisitos, al postular y al procesar un CV', function () {
    Queue::fake();

    app(GestionarRequisitos::class)->quitar($this->backend, $this->aws);
    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $this->postulacion->id && $job->empresaId === $this->nimbus->id);

    $marco = Candidato::factory()->create();
    $nueva = app(PostularCandidato::class)->ejecutar($this->backend, $marco);
    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $nueva->id);

    // La extracción corre de verdad; su evento CvProcesado programa el cálculo.
    Queue::fakeExcept(ExtraerTextoCv::class);
    app(SubirCv::class)->ejecutar($this->elena, UploadedFile::fake()->createWithContent('CV.docx', DocumentosDePrueba::docx('Java y Kubernetes en producción.')), null);
    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $this->postulacion->id);
});

it('explica el puntaje requisito por requisito: lo que aporta cada uno suma el total', function () {
    $resultado = MatchResult::desdeArreglo($this->postulacion->fresh()->match_detalle);
    $explicacion = ExplicacionDelPuntaje::de($resultado, $this->backend->requisitos()->with('competencia')->get());

    expect($explicacion->total())->toBe(3)
        ->and(array_sum(array_column($explicacion->filas, 'aporta')))->toEqualWithDelta($resultado->score, 0.3)
        ->and(array_sum(array_column($explicacion->filas, 'maximo')))->toEqualWithDelta(100, 0.3)
        ->and($explicacion->obligatoriosFaltantes())->toBe(['Certificación AWS'])
        // Primero lo obligatorio que no aparece; después lo dudoso.
        ->and(array_column($explicacion->brechas(), 'competencia'))->toBe(['Certificación AWS', 'Kubernetes'])
        ->and(array_column($explicacion->fortalezas(), 'competencia'))->toBe(['Java']);
});

it('sugiere preguntas para la entrevista a partir de las brechas, sin usar la IA', function () {
    $resultado = MatchResult::desdeArreglo($this->postulacion->fresh()->match_detalle);
    $preguntas = ExplicacionDelPuntaje::de($resultado, $this->backend->requisitos()->with('competencia')->get())->preguntasSugeridas();

    expect($preguntas)->toHaveCount(2)
        ->and($preguntas[0])->toContain('Certificación AWS es obligatorio')
        ->and($preguntas[1])->toContain('Kubernetes solo de forma parcial');
});

it('muestra el resumen, cómo se llega al porcentaje, las brechas y las preguntas', function () {
    $this->get('/matching/'.$this->postulacion->id)
        ->assertOk()
        ->assertSee('1 de 3 requisitos respaldados por el CV')
        ->assertSee('Falta un requisito obligatorio: Certificación AWS.')
        ->assertSee('Puesto 1 de 1 candidato calculado en esta vacante.')
        ->assertSee('Cómo se llega al')
        ->assertSee('Lo que falta o conviene comprobar')
        ->assertSee('Preguntas sugeridas para la entrevista')
        ->assertSee('Evidencia por requisito')
        ->assertSee('role="img"', false);
});

it('«Recalcular» pasa a «Recalculando…», avisa cuánto cambió y deja de esperar', function () {
    Queue::fake();
    // El cálculo de beforeEach es anterior al pedido: el recálculo se pide después.
    Carbon::setTestNow(now()->addMinutes(10));
    $pedido = now();

    $pantalla = Livewire::test(Detalle::class, ['postulacion' => $this->postulacion])
        ->call('recalcular')
        ->assertSee('Recalculando con la IA')
        ->assertSeeHtml('wire:poll.2s');

    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $this->postulacion->id);

    // El requisito que faltaba ya no se pide: el resultado nuevo es más alto.
    Requisito::where('vacante_id', $this->backend->id)->where('id', $this->aws->id)->delete();
    Carbon::setTestNow($pedido->copy()->addSeconds(5));
    // Con Queue::fake() el job no corre solo: se ejecuta como lo haría el worker.
    app()->call([new CalcularMatch($this->nimbus->id, $this->postulacion->id), 'handle']);

    $pantalla->call('$refresh')
        ->assertSee('Coincidencia actualizada: de ')
        ->assertDontSee('Recalculando con la IA')
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertSee('Último cálculo:');

    Carbon::setTestNow();
});

it('«Recalcular» tiene un límite por persona y postulación: pasado, avisa y deja de contar', function () {
    Queue::fake();
    $clave = 'recalcular-matching:'.auth()->id().':'.$this->postulacion->id;
    RateLimiter::clear($clave);

    $pantalla = Livewire::test(Detalle::class, ['postulacion' => $this->postulacion]);

    foreach (range(1, Detalle::RECALCULOS_POR_POSTULACION) as $_) {
        $pantalla->call('recalcular')->assertDontSee('Ya pediste varios recálculos seguidos');
    }

    $pantalla->call('recalcular')->assertSee('Ya pediste varios recálculos seguidos')->assertDontSeeHtml('wire:poll.2s');

    expect(RateLimiter::attempts($clave))->toBe(Detalle::RECALCULOS_POR_POSTULACION); // la sexta no se cuenta ni se pide
    Queue::assertPushed(CalcularMatch::class); // y el trabajo es único por postulación: no se acumula
});

it('si el cálculo no llega a tiempo avisa que sigue en curso', function () {
    Queue::fake();
    Carbon::setTestNow(now()->addMinutes(10));
    $pedido = now();

    $pantalla = Livewire::test(Detalle::class, ['postulacion' => $this->postulacion])->call('recalcular');

    Carbon::setTestNow($pedido->copy()->addMinutes(2));

    $pantalla->call('$refresh')
        ->assertSee('El cálculo sigue en curso')
        ->assertDontSeeHtml('wire:poll.2s');

    Carbon::setTestNow();
});

it('deja la coincidencia vacía si la vacante se queda sin requisitos', function () {
    Requisito::where('vacante_id', $this->backend->id)->delete();

    CalcularMatch::dispatchSync($this->nimbus->id, $this->postulacion->id);

    expect($this->postulacion->fresh()->match_score)->toBeNull();

    $this->get('/matching/'.$this->postulacion->id)
        ->assertOk()
        ->assertSee('Todavía no hay coincidencia calculada');
});

it('no muestra el matching de otra empresa', function () {
    $ajena = app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create();

        return Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($vela)->create())->create();
    });

    $this->get('/matching/'.$ajena->id)->assertNotFound();
});

it('no deja entrar al postulante', function () {
    $postulante = User::factory()->postulante()->create();

    $this->actingAs($postulante)->get('/matching/'.$this->postulacion->id)->assertForbidden();
});
