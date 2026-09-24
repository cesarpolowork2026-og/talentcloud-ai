<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Siempre en la campana; por correo solo si el usuario lo tiene activado en
 * "Mi perfil". A un candidato sin cuenta (AnonymousNotifiable) solo por correo.
 */
trait CanalesSegunPreferencia
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return $notifiable instanceof User && $notifiable->notificaciones_email ? ['database', 'mail'] : ['database'];
    }
}
