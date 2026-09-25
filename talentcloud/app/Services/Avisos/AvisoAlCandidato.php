<?php

namespace App\Services\Avisos;

use App\Models\Candidato;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * Envía un aviso a un candidato: con cuenta de postulante, a su cuenta (campana
 * y correo según su preferencia); sin cuenta, solo por correo si lo tiene.
 */
class AvisoAlCandidato
{
    public function enviar(?Candidato $candidato, Notification $aviso): void
    {
        if ($candidato?->user !== null) {
            $candidato->user->notify($aviso);
        } elseif (filled($candidato?->email)) {
            Notificaciones::route('mail', $candidato->email)->notify($aviso);
        }
    }

    /** "Mis postulaciones" si el candidato tiene cuenta (a quien no la tiene, el correo no lleva enlace). */
    public static function urlPara(?Candidato $candidato): ?string
    {
        return $candidato?->user_id !== null ? route('postulante.postulaciones') : null;
    }
}
