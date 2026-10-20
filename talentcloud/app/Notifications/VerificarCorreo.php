<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Enlace de verificación del correo, en cola: si el servidor de correo no responde, el
 * registro (o «Reenviar enlace») no falla con un error 500; el trabajo se reintenta.
 */
class VerificarCorreo extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }
}
