<?php

namespace App\DTO;

use App\Models\Entrevista;
use App\Support\EnlaceExterno;
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
        public ?int $candidatoId = null,
        /** Dónde es, ya con su etiqueta («Dirección: Av. Larco 1301»): lo escribió una persona, así que va como texto literal. */
        public ?string $lugar = null,
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
            // Solo http/https, aunque el formulario ya lo valide: el aviso lo pone como botón de un correo.
            enlace: EnlaceExterno::seguro($entrevista->enlace),
            candidatoId: $entrevista->postulacion?->candidato_id,
            lugar: $entrevista->lugar !== null ? $entrevista->modalidad->etiquetaDelLugar().': '.$entrevista->lugar : null,
        );
    }
}
