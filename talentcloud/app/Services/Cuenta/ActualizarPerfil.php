<?php

namespace App\Services\Cuenta;

use App\DTO\FichaDelPostulante;
use App\Events\CorreoCambiado;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Mi perfil": nombre, correo y preferencia de avisos. Si la cuenta es de un
 * postulante, su ficha de candidato (la que ven las empresas) se actualiza
 * con el mismo nombre y correo y, si se envía, con su titular y teléfono.
 * Un correo nuevo debe verificarse otra vez y se avisa al anterior (CorreoCambiado). Que quien lo cambia conozca la
 * contraseña actual lo pide el formulario, no este servicio.
 */
class ActualizarPerfil
{
    public function ejecutar(User $user, string $nombre, string $email, bool $notificacionesEmail, ?FichaDelPostulante $ficha = null): User
    {
        $correoAnterior = $user->email;

        $user = DB::transaction(function () use ($user, $nombre, $email, $notificacionesEmail, $ficha): User {
            $user->fill(['name' => $nombre, 'email' => $email, 'notificaciones_email' => $notificacionesEmail]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            $datosDeLaFicha = ['nombre' => $user->name, 'email' => $user->email];

            if ($ficha !== null) {
                $datosDeLaFicha['titular'] = $ficha->titular;
                $datosDeLaFicha['telefono'] = $ficha->telefono;
            }

            $user->candidato?->update($datosDeLaFicha);

            return $user;
        });

        if ($user->email !== $correoAnterior) {
            CorreoCambiado::dispatch($user, $correoAnterior);
        }

        return $user;
    }
}
