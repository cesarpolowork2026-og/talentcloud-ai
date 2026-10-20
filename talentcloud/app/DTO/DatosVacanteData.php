<?php

namespace App\DTO;

use App\Enums\Jornada;
use App\Enums\Modalidad;

final readonly class DatosVacanteData
{
    public function __construct(
        public string $titulo,
        public ?string $descripcion,
        public ?string $ubicacion,
        public Modalidad $modalidad,
        public ?Jornada $jornada = null,
        public ?int $sueldoMinimo = null,
        public ?int $sueldoMaximo = null,
    ) {}
}
