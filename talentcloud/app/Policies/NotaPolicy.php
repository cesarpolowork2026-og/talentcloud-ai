<?php

namespace App\Policies;

use App\Models\Nota;
use App\Models\User;

/** Una nota interna solo la borra quien la escribió (leer y escribir: PostulacionPolicy::gestionarNotas). */
class NotaPolicy
{
    public function delete(User $user, Nota $nota): bool
    {
        return $nota->autor_id === $user->id;
    }
}
