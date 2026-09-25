<?php

namespace App\DTO;

use App\Enums\NivelCoincidencia;
use Carbon\CarbonInterface;

/**
 * Un resultado de la IA que un reclutador marcó como incorrecto, con lo que la
 * IA dijo en ese momento (nivel y evidencia) para poder revisarlo.
 */
final readonly class MarcaDeResultadoIncorrecto
{
    public function __construct(
        public string $empresa,
        public string $vacante,
        public string $requisito,
        public ?NivelCoincidencia $nivelDeLaIa,
        public ?string $evidencia,
        public string $marcadaPor,
        public CarbonInterface $fecha,
        public ?string $comentario,
        public string $urlMatching,
    ) {}
}
