<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** La persona cambió su contraseña desde "Mi perfil" (la recuperación emite PasswordReset de Laravel). */
class ContrasenaCambiada
{
    use Dispatchable;

    public function __construct(public readonly User $usuario) {}
}
