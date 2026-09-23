<?php

namespace App\DTO;

use App\Enums\Rol;

/** Datos del formulario "Invitar reclutador" / "Invitar usuario". */
final readonly class InvitacionData
{
    public function __construct(
        public ?string $nombre,
        public string $email,
        public Rol $rol,
    ) {}
}
