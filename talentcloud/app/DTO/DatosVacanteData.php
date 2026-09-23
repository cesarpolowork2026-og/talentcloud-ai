<?php

namespace App\DTO;

use App\Enums\Modalidad;

final readonly class DatosVacanteData
{
    public function __construct(
        public string $titulo,
        public ?string $descripcion,
        public ?string $ubicacion,
        public Modalidad $modalidad,
    ) {}
}
