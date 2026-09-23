<?php

namespace App\DTO;

/** Datos del formulario de registro de postulante. */
final readonly class RegistroPostulanteData
{
    public function __construct(
        public string $nombre,
        public string $email,
        public string $password,
        public bool $aceptaTratamientoDeDatos,
    ) {}
}
