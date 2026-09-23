<?php

use App\Enums\MotorMatching;
use App\Matching\MatchResult;

/*
 * El contrato con el servicio de IA (versión 1) no puede cambiar sin querer: estos ejemplos
 * los valida también el pytest de talentcloud-ai (tests/test_contrato.py).
 */

it('envía al servicio de IA exactamente el cuerpo del ejemplo del contrato', function () {
    $ejemplo = ejemploDeContrato('match_input');

    $entrada = entradaDeEjemplo($ejemplo);

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
