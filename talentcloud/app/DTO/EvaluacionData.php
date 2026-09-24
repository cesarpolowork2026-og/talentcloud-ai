<?php

namespace App\DTO;

use App\Enums\CriterioEvaluacion;
use App\Enums\EstadoEvaluacion;

/** Datos del formulario de evaluación: un puntaje (0–10) por criterio, comentario y estado. */
final readonly class EvaluacionData
{
    /**
     * @param  array<string, float>  $puntajes  valor de CriterioEvaluacion => puntaje
     */
    public function __construct(
        public array $puntajes,
        public ?string $comentario,
        public EstadoEvaluacion $estado,
    ) {}

    /**
     * Criterios en el orden de CriterioEvaluacion, como se guardan en evaluaciones.criterios.
     *
     * @return list<array{nombre: string, puntaje: float}>
     */
    public function criterios(): array
    {
        return array_map(
            fn (CriterioEvaluacion $c) => ['nombre' => $c->etiqueta(), 'puntaje' => (float) ($this->puntajes[$c->value] ?? 0)],
            CriterioEvaluacion::cases(),
        );
    }

    /**
     * Criterios sin puntaje o fuera de 0–10.
     *
     * @return list<CriterioEvaluacion>
     */
    public function criteriosNoValidos(): array
    {
        return array_values(array_filter(
            CriterioEvaluacion::cases(),
            fn (CriterioEvaluacion $c) => ! isset($this->puntajes[$c->value])
                || $this->puntajes[$c->value] < 0
                || $this->puntajes[$c->value] > CriterioEvaluacion::PUNTAJE_MAXIMO,
        ));
    }
}
