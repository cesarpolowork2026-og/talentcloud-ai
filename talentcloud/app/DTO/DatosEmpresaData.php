<?php

namespace App\DTO;

/** Datos del perfil de la empresa ("Completa el perfil de tu empresa"). */
final readonly class DatosEmpresaData
{
    public function __construct(
        public string $nombre,
        public ?string $sector,
        public ?string $sitioWeb,
        public ?string $descripcion,
    ) {}
}
