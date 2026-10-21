<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** La persona cambió el correo de su cuenta desde «Mi perfil»: se avisa al correo anterior. */
class CorreoCambiado
{
    use Dispatchable;

    public function __construct(
        public readonly User $usuario,
        public readonly string $correoAnterior,
    ) {}
}
