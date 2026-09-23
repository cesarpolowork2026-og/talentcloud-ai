<?php

use App\Enums\MotorMatching;
use App\Enums\TipoRequisito;
use App\Matching\MatchInput;
use App\Matching\MatchResult;
use App\Matching\RequisitoInput;

/*
 * El contrato con el servicio de IA (versión 1) no puede cambiar sin querer: estos ejemplos
 * los valida también el pytest de talentcloud-ai (tests/test_contrato.py).
 */

function ejemploDeContrato(string $nombre): array
{
    $ruta = dirname(__DIR__, 4).'/talentcloud-ai/contrato/ejemplos/'.$nombre.'.json';

    return json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
}

it('envía al servicio de IA exactamente el cuerpo del ejemplo del contrato', function () {
    $ejemplo = ejemploDeContrato('match_input');

    $entrada = new MatchInput(
        postulacionId: $ejemplo['postulacion_id'],
        vacante: $ejemplo['vacante']['titulo'],
        descripcionVacante: $ejemplo['vacante']['descripcion'],
        requisitos: array_map(fn (array $r) => new RequisitoInput(
            id: $r['id'],
            competencia: $r['competencia'],
            sinonimos: $r['sinonimos'],
            tipo: TipoRequisito::from($r['tipo']),
            peso: $r['peso'],
            aniosMinimos: $r['anios_minimos'],
            descripcion: $r['descripcion'],
        ), $ejemplo['requisitos']),
        cvId: $ejemplo['cv']['id'],
        textoCv: $ejemplo['cv']['texto'],
    );

    expect($entrada->aArreglo())->toBe($ejemplo);
});

it('lee la respuesta del servicio de IA sin perder información', function () {
    $ejemplo = ejemploDeContrato('match_result');

    $resultado = MatchResult::desdeArreglo($ejemplo);

    expect($resultado->motor)->toBe(MotorMatching::Http)
        ->and($resultado->scoreRedondeado())->toBe(78)
        ->and($resultado->requisitos)->toHaveCount(6)
        ->and($resultado->aArreglo())->toEqual($ejemplo);
});
