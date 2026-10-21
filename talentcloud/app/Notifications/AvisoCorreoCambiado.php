<?php

namespace App\Notifications;

use App\Support\Formato;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de seguridad al correo ANTERIOR de una cuenta: el correo cambió. Es la única forma de que quien tenía la
 * cuenta se entere si no fue quien lo cambió; por eso no hay un enlace de recuperación (el correo ya no es el de la
 * cuenta) sino el contacto de soporte. Llega siempre por correo. Lleva solo texto, no modelos.
 */
class AvisoCorreoCambiado extends Notification implements ShouldQueue
{
    use Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $soporte,
    ) {
        $this->afterCommit();
    }

    /** `$correoNuevo` se muestra a medias («ma***@correo.com»): basta para reconocerlo sin regalarlo. */
    public static function de(string $correoNuevo, string $soporte): self
    {
        return new self(
            'El correo de tu cuenta de TalentCloud AI se cambió el '.Formato::fechaHora(now()).' a '.self::enmascarar($correoNuevo).'.',
            $soporte,
        );
    }

    public static function enmascarar(string $correo): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($usuario, 0, 2).'***@'.$dominio;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('El correo de tu cuenta de TalentCloud AI cambió')
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line('Desde ahora los avisos y la recuperación de la cuenta llegan al correo nuevo.')
            ->line('Si fuiste tú, no tienes que hacer nada. Si no fuiste tú, escribe de inmediato a '.TextoDeCorreo::literal($this->soporte).' para recuperar tu cuenta.');
    }
}
