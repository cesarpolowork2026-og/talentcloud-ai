<?php

namespace App\DTO;

/** Alta de un candidato por el personal de una empresa. */
final readonly class NuevoCandidatoData
{
    public function __construct(
        public string $nombre,
        public ?string $email,
        public ?string $telefono,
        public ?string $titular,
        public bool $autorizoTratamientoDeDatos,
    ) {}
}
