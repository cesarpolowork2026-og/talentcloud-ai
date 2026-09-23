<?php

namespace App\DTO;

/** Datos del formulario "Crear empresa". */
final readonly class RegistroEmpresaData
{
    public function __construct(
        public string $nombreEmpresa,
        public string $nombre,
        public string $email,
        public string $password,
    ) {}
}
