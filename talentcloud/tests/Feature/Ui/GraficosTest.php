<?php

use App\Enums\NivelCoincidencia;
use Illuminate\Support\Facades\Blade;

/*
 * Gráficos de datos (embudo, mapa de calor, radar comparativo y minigráfico): el marcado fija que el dato nunca
 * depende solo del color ni del dibujo (cifras en texto, símbolos en las celdas, tabla real, leyenda con trazo).
 */

function etapasDeEjemplo(): array
{
    return [
        ['clave' => 'postulado', 'etiqueta' => 'Postulado', 'total' => 100, 'porcentaje' => 100, 'conversion' => null],
        ['clave' => 'en_revision', 'etiqueta' => 'En revisión', 'total' => 48, 'porcentaje' => 48, 'conversion' => 48],
        ['clave' => 'entrevista', 'etiqueta' => 'Entrevista', 'total' => 24, 'porcentaje' => 24, 'conversion' => 50],
        ['clave' => 'oferta', 'etiqueta' => 'Oferta', 'total' => 6, 'porcentaje' => 6, 'conversion' => 25],
        ['clave' => 'contratado', 'etiqueta' => 'Contratado', 'total' => 0, 'porcentaje' => 0, 'conversion' => 0],
    ];
}

it('el embudo dibuja una fila por etapa con su cifra, su conversión y una forma que se estrecha', function () {
    $html = Blade::render('<x-grafico.embudo :etapas="$e" />', ['e' => etapasDeEjemplo()]);

    expect(substr_count($html, 'class="embudo-fila"'))->toBe(5)
        ->and($html)->toContain('aria-label="Postulaciones que alcanzaron cada etapa"')
        ->and($html)->toContain('48 % de la etapa anterior')
        ->and($html)->toContain('48 % del total')
        // La primera forma ocupa todo el ancho arriba y baja hasta el 48 % de la siguiente.
        ->and($html)->toContain('polygon(0% 0, 100% 0, 74% 100%, 26% 100%)')
        // La forma es decorativa: lo que importa está en texto.
        ->and(substr_count($html, 'class="embudo-forma"'))->toBe(5)
        ->and(substr_count($html, 'aria-hidden="true"'))->toBe(5);
});

it('el embudo mantiene visible una etapa vacía (la cifra real va en texto) y no se sale del 100 %', function () {
    $html = Blade::render('<x-grafico.embudo :etapas="$e" />', ['e' => etapasDeEjemplo()]);

    // Contratado: 0 postulaciones → forma mínima del 5 %; la cifra dice 0.
    expect($html)->toContain('polygon(47.5% 0, 52.5% 0, 52.5% 100%, 47.5% 100%)')
        ->and($html)->toMatch('/0\s*<small>0 % del total<\/small>/');
});

it('el mapa de calor es una tabla real con un símbolo y un texto por celda', function () {
    $html = Blade::render('<x-grafico.mapa-de-calor :columnas="$c" :filas="$f" />', [
        'c' => [['competencia' => 'Java', 'obligatorio' => true], ['competencia' => 'Docker', 'obligatorio' => false], ['competencia' => 'Git', 'obligatorio' => false]],
        'f' => [
            ['clave' => 1, 'titulo' => 'Elena Ruiz', 'subtitulo' => 'Backend', 'url' => '/c/1', 'porcentaje' => 82, 'niveles' => [NivelCoincidencia::Coincide, NivelCoincidencia::Revisar, NivelCoincidencia::NoEncontrado]],
            ['clave' => 2, 'titulo' => 'Marco Díaz', 'subtitulo' => null, 'url' => '/c/2', 'porcentaje' => null, 'niveles' => [null, null, null]],
        ],
    ]);

    expect($html)->toContain('<table aria-label="Cobertura de los requisitos de la vacante por candidato">')
        ->and(substr_count($html, 'scope="col"'))->toBe(5)   // candidato + 3 requisitos + coincidencia
        ->and(substr_count($html, 'scope="row"'))->toBe(2)
        ->and($html)->toContain('mapa-coincide')->toContain('mapa-revisar')->toContain('mapa-no')->toContain('mapa-sin')
        // El color nunca va solo: símbolo visible y nivel dicho para los lectores de pantalla.
        ->and($html)->toContain('✓')->toContain('?')->toContain('✗')
        ->and($html)->toContain('Java: Coincide')->toContain('Docker: Revisar')->toContain('Git: No encontrado')->toContain('Java: Sin calcular')
        ->and($html)->toContain('>Java*</span>')   // obligatorio marcado
        ->and($html)->toContain('82 %')->toContain('—')
        ->and($html)->toContain('href="/c/1"');
});

it('el mapa de calor escapa los nombres escritos por personas', function () {
    $html = Blade::render('<x-grafico.mapa-de-calor :columnas="$c" :filas="$f" />', [
        'c' => [['competencia' => '<script>alert(1)</script>', 'obligatorio' => false]],
        'f' => [['clave' => 1, 'titulo' => '"><img src=x onerror=alert(2)>', 'subtitulo' => null, 'url' => '/c/1', 'porcentaje' => 10, 'niveles' => [NivelCoincidencia::Coincide]]],
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<img src=x');
});

it('el radar superpone de dos a tres personas con leyenda y sin pintar nada si no se puede comparar', function () {
    $ejes = [['etiqueta' => 'Conocimiento técnico'], ['etiqueta' => 'Comunicación'], ['etiqueta' => 'Resolución de problemas'], ['etiqueta' => 'Ajuste cultural']];
    $serie = fn (string $nombre, array $v, ?float $promedio) => ['nombre' => $nombre, 'valores' => $v, 'promedio' => $promedio];

    $html = Blade::render('<x-grafico.radar-comparativo :ejes="$e" :series="$s" />', ['e' => $ejes, 's' => [
        $serie('Elena Ruiz', [9, 8, 9, 8.4], 8.6),
        $serie('Marco Díaz', [5, null, 6, 7], 6.0),
    ]]);

    expect($html)->toContain('aria-label="Personas comparadas"')
        ->and($html)->toContain('Elena Ruiz')->toContain('8,6/10')->toContain('Marco Díaz')
        ->and(substr_count($html, 'class="radar-poligono"'))->toBe(2)
        // Los cuatro ejes con su etiqueta (la larga, en dos líneas).
        ->and($html)->toContain('Comunicación')->toContain('Resolución')->toContain('de problemas')
        // Un criterio sin evaluar se dice en el tooltip y no se pinta como un cero real.
        ->and($html)->toContain('Sin evaluar en este criterio');

    // Una sola persona, o menos de tres ejes: no hay nada que comparar.
    expect(Blade::render('<x-grafico.radar-comparativo :ejes="$e" :series="$s" />', ['e' => $ejes, 's' => [$serie('Solo', [1, 2, 3, 4], 2.5)]]))->not->toContain('<svg')
        ->and(Blade::render('<x-grafico.radar-comparativo :ejes="$e" :series="$s" />', ['e' => array_slice($ejes, 0, 2), 's' => [$serie('A', [1, 2], 1.5), $serie('B', [3, 4], 3.5)]]))->not->toContain('<svg');
});

it('el radar no pinta más de tres series', function () {
    $ejes = [['etiqueta' => 'A'], ['etiqueta' => 'B'], ['etiqueta' => 'C']];
    $series = array_map(fn (int $i) => ['nombre' => 'Persona '.$i, 'valores' => [5, 6, 7], 'promedio' => 6.0], range(1, 5));

    $html = Blade::render('<x-grafico.radar-comparativo :ejes="$e" :series="$s" />', ['e' => $ejes, 's' => $series]);

    expect(substr_count($html, 'class="radar-poligono"'))->toBe(3)->and($html)->not->toContain('Persona 4');
});

it('el indicador muestra la variación con signo, símbolo y texto, y el minigráfico solo como contexto', function () {
    $sube = Blade::render('<x-ui.stat label="Postulaciones" value="40" :variacion="25.5" nota="frente al periodo anterior" :serie="[1,3,2,5]" />');
    $baja = Blade::render('<x-ui.stat label="Postulaciones" value="40" :variacion="-10.0" />');
    $nada = Blade::render('<x-ui.stat label="Postulaciones" value="40" nota="Sin periodo anterior" />');

    expect($sube)->toContain('variacion-sube')->toContain('+25,5 %')->toContain('Sube')->toContain('frente al periodo anterior')
        ->and($sube)->toContain('class="viz minilinea')->toContain('aria-hidden="true" focusable="false"')
        ->and($baja)->toContain('variacion-baja')->toContain('-10 %')->toContain('Baja')->not->toContain('minilinea')
        ->and($nada)->not->toContain('variacion')->toContain('Sin periodo anterior');
});

it('el contenedor desplazable del mapa contiene a los textos solo para lectores (si no, ensanchan la página en móvil)', function () {
    $css = file_get_contents(resource_path('css/graficos.css'));

    expect($css)->toMatch('/\.mapa\s*\{[^}]*position:\s*relative;[^}]*overflow-x:\s*auto/');
});
