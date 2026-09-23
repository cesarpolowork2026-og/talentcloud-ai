<?php

namespace App\Ia;

use App\Enums\MotorMatching;
use App\Enums\NivelCoincidencia;
use App\Matching\MatchInput;
use App\Matching\MatchResult;
use App\Matching\RequisitoInput;
use App\Matching\RequisitoMatch;

/**
 * Lectura estricta de la respuesta de POST /matching/evaluar (contrato versión 1).
 *
 * A diferencia de MatchResult::desdeArreglo(), que tolera lo guardado en
 * match_detalle, aquí cualquier desvío del contrato es un error: la respuesta
 * debe traer exactamente un resultado por cada requisito enviado.
 */
final class RespuestaDeMatching
{
    /**
     * @param  array<mixed>  $respuesta
     */
    public static function leer(array $respuesta, MatchInput $entrada): MatchResult
    {
        $campos = new Campos($respuesta);

        if ($campos->entero('version') !== MatchResult::VERSION) {
            throw RespuestaIaNoValida::campo('version');
        }
        if ($campos->texto('motor') !== MotorMatching::Http->value) {
            throw RespuestaIaNoValida::campo('motor');
        }

        $score = $campos->decimalOpcional('score');
        if ($score === null || $score < 0 || $score > 100) {
            throw RespuestaIaNoValida::campo('score');
        }

        $requisitos = array_map(self::requisito(...), $campos->objetos('requisitos'));
        self::verificarQueCubreLaEntrada($requisitos, $entrada);

        return new MatchResult($score, $requisitos, MotorMatching::Http, $campos->textoOpcional('resumen'));
    }

    private static function requisito(Campos $campos): RequisitoMatch
    {
        $porcentaje = $campos->entero('porcentaje');
        $nivel = NivelCoincidencia::tryFrom($campos->texto('nivel'));

        if ($porcentaje < 0 || $porcentaje > 100) {
            throw RespuestaIaNoValida::campo('requisitos[].porcentaje');
        }
        if ($nivel === null) {
            throw RespuestaIaNoValida::campo('requisitos[].nivel');
        }

        return new RequisitoMatch(
            requisitoId: $campos->entero('requisito_id'),
            competencia: $campos->texto('competencia'),
            porcentaje: $porcentaje,
            nivel: $nivel,
            evidencia: $campos->textoOpcional('evidencia'),
            motivo: $campos->texto('motivo'),
        );
    }

    /**
     * @param  list<RequisitoMatch>  $requisitos
     */
    private static function verificarQueCubreLaEntrada(array $requisitos, MatchInput $entrada): void
    {
        $recibidos = array_map(fn (RequisitoMatch $r) => $r->requisitoId, $requisitos);
        $enviados = array_map(fn (RequisitoInput $r) => $r->id, $entrada->requisitos);

        sort($recibidos);
        sort($enviados);

        if ($recibidos !== $enviados) {
            throw RespuestaIaNoValida::campo('requisitos (no corresponden a los enviados)');
        }
    }
}
