<?php

use App\Ia\HttpServicioIa;
use App\Ia\RespuestaIaNoValida;
use App\Ia\ServicioIaNoDisponible;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * Cliente HTTP del servicio de IA contra los ejemplos del contrato
 * (talentcloud-ai/contrato/ejemplos), los mismos que valida el pytest.
 */

function ejemploIa(string $nombre): array
{
    $ruta = dirname(__DIR__, 4).'/talentcloud-ai/contrato/ejemplos/'.$nombre.'.json';

    return json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
}

function clienteIa(?string $apiKey = 'clave', int $reintentos = 2): HttpServicioIa
{
    return new HttpServicioIa(habilitado: true, url: 'http://ia.test', apiKey: $apiKey, timeout: 5, reintentos: $reintentos);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(); // las esperas entre reintentos
});

it('envía el análisis de CV con la clave y el cuerpo del contrato', function () {
    Http::fake(['ia.test/cv/analizar' => Http::response(ejemploIa('analisis_cv'))]);
    $entrada = ejemploIa('analisis_cv_input');

    clienteIa()->analizarCv($entrada['cv']['id'], $entrada['cv']['texto'], $entrada['catalogo']);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ia.test/cv/analizar'
        && $request->hasHeader('X-Api-Key', 'clave')
        && $request->data() === $entrada);
});

it('lee el análisis de CV del ejemplo del contrato', function () {
    Http::fake(['ia.test/cv/analizar' => Http::response(ejemploIa('analisis_cv'))]);

    $analisis = clienteIa()->analizarCv(42, 'texto', []);

    expect($analisis->competencias)->toHaveCount(4)
        ->and(array_map(fn ($c) => $c->nombre, $analisis->competenciasAcreditadas()))->toBe(['Python', 'PostgreSQL', 'Google Cloud'])
        ->and($analisis->competencias[0]->anios)->toBe(4.0)
        ->and($analisis->fragmentos)->toHaveCount(4)
        ->and($analisis->fragmentos[0]->embedding)->toHaveCount(384)
        ->and($analisis->modeloEmbeddings)->toBe('determinista-hashing')
        ->and($analisis->experienciaTotalAnios)->toBe(3.7);

    // Lo que se guarda en el CV no duplica el texto de las secciones ni los vectores.
    $datos = $analisis->datosEstructurados();
    expect($datos['secciones'][1])->toBe(['nombre' => 'perfil', 'titulo' => 'Perfil', 'orden' => 1])
        ->and($datos)->not->toHaveKey('fragmentos')
        ->and($datos['cargos'][0]['organizacion'])->toBe('Andes Analytics');
});

it('genera embeddings conservando el id de cada texto', function () {
    Http::fake(['ia.test/requisitos/embeddings' => Http::response([
        'version' => 1, 'modelo' => 'modelo-x', 'dimensiones' => 2,
        'embeddings' => [['id' => 7, 'vector' => [0.6, 0.8]], ['id' => 3, 'vector' => [1, 0]]],
    ])]);

    $generados = clienteIa()->generarEmbeddings([7 => 'Java', 3 => 'Docker']);

    expect($generados->vectorDe(7))->toBe([0.6, 0.8])
        ->and($generados->vectorDe(3))->toBe([1.0, 0.0])
        ->and($generados->modelo)->toBe('modelo-x');
    Http::assertSent(fn (Request $request) => $request['textos'] === [['id' => 7, 'texto' => 'Java'], ['id' => 3, 'texto' => 'Docker']]);
});

it('no envía la cabecera de clave si no hay clave configurada', function () {
    Http::fake(['ia.test/cv/analizar' => Http::response(ejemploIa('analisis_cv'))]);

    clienteIa(apiKey: null)->analizarCv(1, 'texto', []);

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('X-Api-Key'));
});

it('reintenta los errores transitorios del servicio', function () {
    Http::fake(['ia.test/cv/analizar' => Http::sequence()
        ->push(['detail' => 'ocupado'], 503)
        ->push(ejemploIa('analisis_cv'))]);

    expect(clienteIa()->analizarCv(1, 'texto', [])->fragmentos)->toHaveCount(4);
    Http::assertSentCount(2);
});

it('informa que el servicio no está disponible tras agotar los reintentos', function () {
    Http::fake(['ia.test/*' => Http::response(['detail' => 'Error interno del servicio de IA.'], 500)]);

    expect(fn () => clienteIa(reintentos: 1)->analizarCv(1, 'texto', []))
        ->toThrow(ServicioIaNoDisponible::class, 'El servicio de IA respondió con un error (500).');
    Http::assertSentCount(2);
});

it('informa que el servicio no está disponible si no hay conexión', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => clienteIa(reintentos: 0)->analizarCv(1, 'texto', []))
        ->toThrow(ServicioIaNoDisponible::class, 'No se pudo conectar con el servicio de IA.');
});

it('no reintenta una petición rechazada', function () {
    Http::fake(['ia.test/*' => Http::response(['detail' => 'Clave de API no válida.'], 401)]);

    expect(fn () => clienteIa()->analizarCv(1, 'texto', []))
        ->toThrow(RespuestaIaNoValida::class, 'El servicio de IA rechazó la petición (401).');
    Http::assertSentCount(1);
});

it('rechaza respuestas fuera de contrato', function (array $respuesta, string $campo) {
    Http::fake(['ia.test/*' => Http::response($respuesta)]);

    expect(fn () => clienteIa()->analizarCv(1, 'texto', []))
        ->toThrow(RespuestaIaNoValida::class, "campo \"{$campo}\"");
})->with([
    'otra versión' => fn () => [[...ejemploIa('analisis_cv'), 'version' => 2], 'version'],
    'sin modelo' => fn () => [array_diff_key(ejemploIa('analisis_cv'), ['modelo_embeddings' => 1]), 'modelo_embeddings'],
    'embedding vacío' => function () {
        $respuesta = ejemploIa('analisis_cv');
        $respuesta['fragmentos'][1]['embedding'] = [];

        return [$respuesta, 'fragmentos[1].embedding'];
    },
    'años como texto' => function () {
        $respuesta = ejemploIa('analisis_cv');
        $respuesta['competencias'][0]['anios'] = 'cuatro';

        return [$respuesta, 'competencias[0].anios'];
    },
]);

it('comprueba si el servicio responde', function () {
    Http::fake(['ia.test/health' => Http::response(['estado' => 'ok'])]);
    expect(clienteIa()->disponible())->toBeTrue();

    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    expect(clienteIa()->disponible())->toBeFalse();
});
