<?php

namespace App\DTO;

use App\Enums\ModalidadEntrevista;
use Carbon\CarbonImmutable;

/** Datos del formulario "Reprogramar entrevista": nueva fecha, duración, modalidad, enlace y lugar. */
final readonly class ReprogramacionData
{
    public function __construct(
        public CarbonImmutable $iniciaAt,
        public int $duracionMin,
        public ModalidadEntrevista $modalidad,
        public ?string $enlace,
        public ?string $lugar = null,
    ) {}

    public function terminaAt(): CarbonImmutable
    {
        return $this->iniciaAt->addMinutes($this->duracionMin);
    }
}
