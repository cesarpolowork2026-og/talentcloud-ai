<?php

namespace App\Matching;

use App\Enums\MotorMatching;
use InvalidArgumentException;

/**
 * Contrato de salida del matching. Lo produce el motor (FakeMatchingEngine o el
 * servicio de IA) y se guarda en postulaciones.match_detalle. Es una sugerencia
 * explicada: no decide ni mueve la postulación.
 */
final readonly class MatchResult
{
    public const VERSION = 1;

    /**
     * @param  list<RequisitoMatch>  $requisitos
     */
    public function __construct(
        public float $score,
        public array $requisitos,
        public MotorMatching $motor,
        public ?string $resumen = null,
    ) {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('El score debe estar entre 0 y 100.');
        }
    }

    public function scoreRedondeado(): int
    {
        return (int) round($this->score);
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'version' => self::VERSION,
            'score' => round($this->score, 2),
            'motor' => $this->motor->value,
            'resumen' => $this->resumen,
            'requisitos' => array_map(fn (RequisitoMatch $r) => $r->aArreglo(), $this->requisitos),
        ];
    }

    /**
     * Lee lo guardado en match_detalle o la respuesta del servicio de IA.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $requisitos = array_values(array_map(
            fn ($r) => RequisitoMatch::desdeArreglo((array) $r),
            array_filter((array) ($datos['requisitos'] ?? []), 'is_array'),
        ));

        return new self(
            score: max(0.0, min(100.0, (float) ($datos['score'] ?? 0))),
            requisitos: $requisitos,
            motor: MotorMatching::tryFrom((string) ($datos['motor'] ?? '')) ?? MotorMatching::Fake,
            resumen: isset($datos['resumen']) && $datos['resumen'] !== '' ? (string) $datos['resumen'] : null,
        );
    }
}
