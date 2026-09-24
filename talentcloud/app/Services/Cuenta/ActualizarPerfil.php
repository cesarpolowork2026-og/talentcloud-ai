<?php

namespace App\Services\Cuenta;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Mi perfil": nombre, correo y preferencia de avisos. Si la cuenta es de un
 * postulante, su ficha de candidato (la que ven las empresas) se actualiza
 * con el mismo nombre y correo. Un correo nuevo debe verificarse otra vez.
 */
class ActualizarPerfil
{
    public function ejecutar(User $user, string $nombre, string $email, bool $notificacionesEmail): User
    {
        return DB::transaction(function () use ($user, $nombre, $email, $notificacionesEmail): User {
            $user->fill(['name' => $nombre, 'email' => $email, 'notificaciones_email' => $notificacionesEmail]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            $user->candidato?->update(['nombre' => $user->name, 'email' => $user->email]);

            return $user;
        });
    }
}
