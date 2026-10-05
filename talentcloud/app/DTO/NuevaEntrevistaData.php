<?php

namespace App\DTO;

use App\Enums\ModalidadEntrevista;
use Carbon\CarbonImmutable;

/** Datos del formulario "Programar entrevista". */
final readonly class NuevaEntrevistaData
{
    public function __construct(
        public int $postulacionId,
        public int $entrevistadorId,
        public CarbonImmutable $iniciaAt,
        public int $duracionMin,
        public ModalidadEntrevista $modalidad,
        public ?string $enlace,
        public ?string $notas,
        /** Mover la postulación a "Entrevista" si aún está en Postulado o En revisión. */
        public bool $moverAEntrevista = false,
    ) {}

    public function terminaAt(): CarbonImmutable
    {
        return $this->iniciaAt->addMinutes($this->duracionMin);
    }
}
