<?php

namespace App\Matching;

use App\Enums\NivelCoincidencia;
use InvalidArgumentException;

/**
 * Resultado de un requisito: porcentaje, nivel y la evidencia textual del CV
 * que lo sustenta (o su ausencia), con el motivo en lenguaje llano.
 */
final readonly class RequisitoMatch
{
    public function __construct(
        public int $requisitoId,
        public string $competencia,
        public int $porcentaje,
        public NivelCoincidencia $nivel,
        public ?string $evidencia,
        public string $motivo,
    ) {
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new InvalidArgumentException('El porcentaje debe estar entre 0 y 100.');
        }
    }

    /**
     * @return array{requisito_id: int, competencia: string, porcentaje: int, nivel: string, evidencia: string|null, motivo: string}
     */
    public function aArreglo(): array
    {
        return [
            'requisito_id' => $this->requisitoId,
            'competencia' => $this->competencia,
            'porcentaje' => $this->porcentaje,
            'nivel' => $this->nivel->value,
            'evidencia' => $this->evidencia,
            'motivo' => $this->motivo,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            requisitoId: (int) ($datos['requisito_id'] ?? 0),
            competencia: (string) ($datos['competencia'] ?? ''),
            porcentaje: max(0, min(100, (int) round((float) ($datos['porcentaje'] ?? 0)))),
            nivel: NivelCoincidencia::tryFrom((string) ($datos['nivel'] ?? '')) ?? NivelCoincidencia::Revisar,
            evidencia: isset($datos['evidencia']) && $datos['evidencia'] !== '' ? (string) $datos['evidencia'] : null,
            motivo: (string) ($datos['motivo'] ?? ''),
        );
    }
}
