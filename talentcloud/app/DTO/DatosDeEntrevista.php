<?php

namespace App\DTO;

use App\Models\Entrevista;
use App\Support\Formato;

/**
 * Lo que un aviso necesita de una entrevista, ya resuelto en texto. Las
 * notificaciones en cola no llevan el modelo: al reconstruirlo en el worker no
 * habría contexto de empresa y el filtro por empresa no lo encontraría.
 */
final readonly class DatosDeEntrevista
{
    public function __construct(
        public string $candidato,
        public string $vacante,
        public string $empresa,
        public string $fecha,
        public int $duracionMin,
        public string $modalidad,
        public ?string $enlace,
    ) {}

    public static function de(Entrevista $entrevista): self
    {
        $entrevista->loadMissing(['postulacion.candidato', 'postulacion.vacante.empresa']);

        return new self(
            candidato: (string) $entrevista->postulacion?->candidato?->nombre,
            vacante: (string) $entrevista->postulacion?->vacante?->titulo,
            empresa: (string) $entrevista->postulacion?->vacante?->empresa?->nombre,
            fecha: Formato::fechaHora($entrevista->inicia_at),
            duracionMin: $entrevista->duracion_min,
            modalidad: $entrevista->modalidad->etiqueta(),
            enlace: $entrevista->enlace,
        );
    }
}
