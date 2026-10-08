<?php

namespace App\Notifications;

use App\Support\Formato;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de seguridad: la contraseña de la cuenta cambió. Llega siempre por correo,
 * aunque la persona haya desactivado los avisos por correo: si no fue ella, es la
 * forma de enterarse de que alguien entró en su cuenta.
 */
class AvisoContrasenaCambiada extends Notification implements ShouldQueue
{
    use Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $urlRecuperar,
    ) {
        $this->afterCommit();
    }

    public static function ahora(): self
    {
        return new self(
            'La contraseña de tu cuenta se cambió el '.Formato::fechaHora(now()).'.',
            route('password.request'),
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{texto: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['texto' => $this->texto];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu contraseña de TalentCloud AI cambió')
            ->greeting('¡Hola!')
            ->line($this->texto)
            ->line('Por seguridad, cerramos las demás sesiones abiertas de tu cuenta.')
            ->line('Si no fuiste tú, recupera tu cuenta ahora y elige una contraseña nueva.')
            ->action('Recuperar mi cuenta', $this->urlRecuperar);
    }
}
