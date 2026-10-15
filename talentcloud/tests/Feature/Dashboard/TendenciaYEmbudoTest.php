<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use App\Livewire\Dashboard\Panel;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Dashboard\TendenciaDePostulaciones;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
 * Evolución de postulaciones (periodo frente al anterior) y conversión entre etapas del embudo.
 * Fecha fija: martes 13 de octubre de 2026. El periodo de 30 días va del 14 de septiembre al 13 de octubre
 * y el anterior, del 15 de agosto al 13 de septiembre.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-13 12:00:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

/** El texto que ve la persona: sin etiquetas y con los espacios y saltos de línea normalizados. */
function textoVisible(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
}

/** Crea `$n` postulaciones recibidas `$haceDias` días antes (a las 10:00). */
function recibidas(Vacante $vacante, int $haceDias, int $n = 1, EtapaPostulacion $etapa = EtapaPostulacion::Postulado): void
{
    Postulacion::factory()->count($n)->paraVacante($vacante)->create([
        'etapa' => $etapa,
        'created_at' => Carbon::today()->subDays($haceDias)->setTime(10, 0),
    ]);
}

it('cuenta las postulaciones de cada día del periodo y del anterior, con sus límites exactos', function () {
    recibidas($this->backend, 0, 3);   // hoy
    recibidas($this->backend, 1, 2);   // ayer
    recibidas($this->backend, 29);     // primer día del periodo (14 sep)
    recibidas($this->backend, 30);     // último día del periodo anterior (13 sep)
    recibidas($this->backend, 59, 4);  // primer día del periodo anterior (15 ago)
    recibidas($this->backend, 60);     // fuera de los dos periodos

    $tendencia = app(TendenciaDePostulaciones::class)->calcular(30);

    expect($tendencia->totalActual())->toBe(6)
        ->and($tendencia->totalAnterior())->toBe(5)
        ->and($tendencia->variacion())->toBe(20.0)
        ->and($tendencia->hayDatos())->toBeTrue()
        ->and($tendencia->cubetas)->toHaveCount(30)
        ->and($tendencia->diasPorCubeta)->toBe(1)
        ->and($tendencia->desde->toDateString())->toBe('2026-09-14')
        ->and($tendencia->hasta->toDateString())->toBe('2026-10-13')
        ->and($tendencia->desdeAnterior->toDateString())->toBe('2026-08-15')
        ->and($tendencia->hastaAnterior->toDateString())->toBe('2026-09-13')
        ->and($tendencia->cubetas[0]['actual'])->toBe(1)       // 14 sep
        ->and($tendencia->cubetas[0]['anterior'])->toBe(4)     // 15 ago
        ->and($tendencia->cubetas[28]['actual'])->toBe(2)      // ayer
        ->and($tendencia->cubetas[29]['actual'])->toBe(3)      // hoy
        ->and($tendencia->cubetas[29]['anterior'])->toBe(1);   // 13 sep, alineado con hoy
});

it('agrupa los periodos largos en bloques para no dibujar más de 30 puntos', function () {
    recibidas($this->backend, 0, 2);
    recibidas($this->backend, 89);

    $noventa = app(TendenciaDePostulaciones::class)->calcular(90);
    $siete = app(TendenciaDePostulaciones::class)->calcular(7);

    expect($noventa->cubetas)->toHaveCount(30)
        ->and($noventa->diasPorCubeta)->toBe(3)
        ->and($noventa->cubetas[0]['actual'])->toBe(1)    // el día 89 atrás cae en el primer bloque
        ->and($noventa->cubetas[29]['actual'])->toBe(2)   // hoy, en el último
        ->and($noventa->totalActual())->toBe(3)
        ->and($siete->cubetas)->toHaveCount(7)
        ->and($siete->totalActual())->toBe(2);
});

it('sin postulaciones en el periodo anterior no inventa una variación', function () {
    recibidas($this->backend, 2, 4);

    $tendencia = app(TendenciaDePostulaciones::class)->calcular(30);

    expect($tendencia->totalActual())->toBe(4)
        ->and($tendencia->variacion())->toBeNull();

    Livewire::test(Panel::class)->assertSee('Sin postulaciones en los 30 días anteriores: no hay con qué comparar.');
});

it('sin ninguna postulación muestra un estado vacío en lugar de un gráfico', function () {
    expect(app(TendenciaDePostulaciones::class)->calcular(30)->hayDatos())->toBeFalse();

    Livewire::test(Panel::class)
        ->assertSee('No hay postulaciones en estos 30 días ni en los 30 anteriores')
        ->assertDontSeeHtml('role="img" aria-label="Postulaciones recibidas');
});

it('solo cuenta las postulaciones de la empresa', function () {
    recibidas($this->backend, 1, 2);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create();
        $vacante = Vacante::factory()->deEmpresa($vela)->enEstado(EstadoVacante::Abierta)->create();
        Postulacion::factory()->count(5)->paraVacante($vacante)->create(['created_at' => Carbon::today()->subDay()->setTime(9, 0)]);
    });

    expect(app(TendenciaDePostulaciones::class)->calcular(30)->totalActual())->toBe(2);

    actuarComo(User::factory()->admin()->create());
    expect(app(TendenciaDePostulaciones::class)->calcular(30)->totalActual())->toBe(7); // el admin SaaS ve toda la plataforma
});

it('dibuja el gráfico con su descripción, sus datos para lectores de pantalla y el resumen del periodo', function () {
    recibidas($this->backend, 0, 3);
    recibidas($this->backend, 30, 2);

    $html = Livewire::test(Panel::class)->assertSee('Evolución de postulaciones')->html();

    expect(textoVisible($html))->toContain('3 postulaciones en los últimos 30 días')
        ->and(textoVisible($html))->toContain('▲ +50 % frente a las 2 del periodo anterior')
        ->and($html)->toContain('role="img" aria-label="Postulaciones recibidas en los últimos 30 días: 3, frente a 2 en los 30 anteriores"')
        ->and($html)->toContain('<caption>Postulaciones recibidas en los últimos 30 días: 3')
        ->and(substr_count($html, '<th scope="row">'))->toBe(30);
});

it('el periodo elegido cambia el gráfico y el embudo, y uno inválido vuelve a 30 días', function () {
    recibidas($this->backend, 40, 2);   // dentro de 90 días, fuera de 30
    recibidas($this->backend, 3, 1);

    expect(app(EmbudoDeSeleccion::class)->calcular(null, 30)->total)->toBe(1)
        ->and(app(EmbudoDeSeleccion::class)->calcular(null, 90)->total)->toBe(3);

    $pantalla = Livewire::test(Panel::class)->assertSet('periodo', 30);
    expect(textoVisible($pantalla->html()))->toContain('1 postulación en los últimos 30 días');

    $pantalla->set('periodo', 90);
    expect(textoVisible($pantalla->html()))->toContain('3 postulaciones en los últimos 90 días')->toContain('(90 días)');

    $pantalla->set('periodo', 45)->assertSet('periodo', 30);

    $this->get('/dashboard?periodo=7')->assertOk()->assertSee('últimos 7 días');
    $this->get('/dashboard?periodo=999')->assertOk()->assertSee('últimos 30 días');
});

it('el embudo muestra qué parte de la etapa anterior llegó a cada etapa y la conversión total', function () {
    recibidas($this->backend, 2, 4, EtapaPostulacion::Postulado);
    recibidas($this->backend, 3, 3, EtapaPostulacion::EnRevision);
    recibidas($this->backend, 4, 2, EtapaPostulacion::Entrevista);
    recibidas($this->backend, 5, 1, EtapaPostulacion::Contratado);

    $embudo = app(EmbudoDeSeleccion::class)->calcular(null, 30);

    // Llegaron: Postulado 10, En revisión 6, Entrevista 3, Oferta 1 (por quien llegó a Contratado), Contratado 1.
    expect(array_column($embudo->etapas, 'total'))->toBe([10, 6, 3, 1, 1])
        ->and(array_column($embudo->etapas, 'conversion'))->toBe([null, 60, 50, 33, 100])
        ->and($embudo->conversionTotal())->toBe(10);

    $texto = textoVisible(Livewire::test(Panel::class)->html());

    expect($texto)->toContain('60 % de la etapa anterior')
        ->toContain('50 % de la etapa anterior')
        ->toContain('De cada 100 postulaciones del periodo, 10 llegaron a Contratado.')
        ->toContain('del total');
});

it('el embudo sin base en la etapa anterior no calcula una conversión', function () {
    recibidas($this->backend, 2, 3, EtapaPostulacion::Postulado);

    $embudo = app(EmbudoDeSeleccion::class)->calcular(null, 30);

    expect(array_column($embudo->etapas, 'conversion'))->toBe([null, 0, null, null, null])
        ->and($embudo->conversionTotal())->toBe(0);
});

it('el componente de líneas se dibuja con una sola serie, ceros y un único punto sin romperse', function () {
    $html = Blade::render('<x-grafico.lineas :etiquetas="$e" :series="$s" descripcion="Prueba" unidad="cosas" />', [
        'e' => ['1 oct'],
        's' => [['nombre' => 'Única', 'valores' => [0], 'principal' => true]],
    ]);

    expect($html)->toContain('role="img"')->and($html)->toContain('Única (cosas)');

    $html = Blade::render('<x-grafico.lineas :etiquetas="$e" :series="$s" descripcion="Prueba" />', [
        'e' => ['a', 'b', 'c'],
        's' => [
            ['nombre' => 'Actual', 'valores' => [1, 13, 4], 'principal' => true],
            ['nombre' => 'Anterior', 'valores' => [0, 2, 9]],
        ],
    ]);

    // Escala entera hasta 15 (13 de máximo, pasos de 5): 0, 5, 10 y 15.
    expect($html)->toContain('>15</text>')->and($html)->toContain('stroke-dasharray="5 4"')->and(substr_count($html, 'x-show="activo ==='))->toBeGreaterThan(5);
});

it('da accesos directos junto a la atención y muestra qué parte de las postulaciones llegó por cuenta del postulante', function () {
    recibidas($this->backend, 2, 3);
    Postulacion::factory()->paraVacante($this->backend)->create(['origen' => OrigenPostulacion::Postulante, 'created_at' => Carbon::today()->subDay()->setTime(9, 0)]);

    $html = Livewire::test(Panel::class)->html();

    expect($html)->toContain('Ir directo a:')
        ->and($html)->toContain(route('seleccion.index'))
        ->and($html)->toContain(route('entrevistas.index'))
        ->and(textoVisible($html))->toContain('25 % llegó por cuenta del postulante');
});

it('el dashboard no repite la tendencia en un indicador y el embudo dibuja una forma por etapa', function () {
    recibidas($this->backend, 2, 4, EtapaPostulacion::Postulado);
    recibidas($this->backend, 40, 2, EtapaPostulacion::Postulado);

    $html = Livewire::test(Panel::class)->html();

    expect($html)->not->toContain('Postulaciones · 30 días')
        ->and($html)->not->toContain('minilinea')
        ->and($html)->toContain('Evolución de postulaciones')   // la tendencia vive solo en su gráfico
        ->and(substr_count($html, 'class="embudo-fila"'))->toBe(5)
        ->and($html)->toContain('aria-label="Postulaciones que alcanzaron cada etapa"');
});

it('mide los días medios en cada etapa ya terminada, sin contar a quien sigue en ella', function () {
    // Se usa la fecha de hoy fija (13 oct). A: Postulado 2 d → En revisión 4 d → Entrevista (sigue ahí).
    // B: Postulado 4 d → En revisión 6 d → Descartado (sin salida: no cuenta como etapa terminada del embudo).
    $recorrido = function (array $pasos) {
        [$primeraEtapa, $primerDia] = $pasos[0];
        Carbon::setTestNow(Carbon::parse('2026-10-13 12:00')->subDays($primerDia));
        $p = Postulacion::factory()->paraVacante($this->backend)->create(['etapa' => $primeraEtapa, 'created_at' => now()]);

        foreach (array_slice($pasos, 1) as [$etapa, $dia]) {
            Carbon::setTestNow(Carbon::parse('2026-10-13 12:00')->subDays($dia));
            $p->etapa = $etapa;
            $p->save();
        }
        Carbon::setTestNow('2026-10-13 12:00:00');
    };

    $recorrido([[EtapaPostulacion::Postulado, 12], [EtapaPostulacion::EnRevision, 10], [EtapaPostulacion::Entrevista, 6]]);
    $recorrido([[EtapaPostulacion::Postulado, 14], [EtapaPostulacion::EnRevision, 10], [EtapaPostulacion::Descartado, 4]]);

    $tiempo = collect(app(EmbudoDeSeleccion::class)->calcular(null, 30)->tiempoPorEtapa)->keyBy(fn (array $t) => $t['etapa']->value);

    // Postulado: (2 + 4) / 2 = 3 d, con 2 estancias. En revisión: (4 + 6) / 2 = 5 d, con 2 estancias.
    expect($tiempo['postulado']['dias'])->toBe(3.0)
        ->and($tiempo['postulado']['muestras'])->toBe(2)
        ->and($tiempo['en_revision']['dias'])->toBe(5.0)
        // Entrevista: la única postulación sigue en ella, no hay estancia terminada. Contratado nunca se mide.
        ->and($tiempo)->not->toHaveKey('entrevista')
        ->and($tiempo)->not->toHaveKey('contratado');

    $html = Livewire::test(Panel::class)->html();
    expect($html)->toContain('Tiempo en cada etapa')->toContain('3 días')->toContain('5 días')
        ->and(substr_count($html, 'class="barras-fila"'))->toBe(2)
        ->and(substr_count($html, 'barras-barra-mayor'))->toBe(1);

    // Filtrar por una vacante sin postulaciones: sin datos, la tarjeta no aparece.
    $otra = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();
    expect(app(EmbudoDeSeleccion::class)->calcular($otra->id, 30)->tiempoPorEtapa)->toBe([]);
});
