<?php

namespace App\DTO;

/** Datos de la ficha de candidato que el postulante edita en "Mi perfil" (null = vacío). */
final readonly class FichaDelPostulante
{
    public function __construct(
        public ?string $titular,
        public ?string $telefono,
    ) {}
}
