<?php

namespace App\DTO;

use App\Enums\TipoRequisito;

/** Un requisito del editor de la vacante. */
final readonly class RequisitoData
{
    public function __construct(
        public string $competencia,
        public TipoRequisito $tipo,
        public int $peso,
        public ?int $aniosMinimos,
        public ?string $descripcion,
    ) {}
}
