<?php

namespace App\Notifications;

use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a la cuenta de empresa: no se pudo cobrar su plan. Llega siempre por
 * correo, aunque haya desactivado los avisos por correo: si nadie actualiza la
 * tarjeta, la empresa pasará al plan Gratis. Lleva solo texto, no modelos.
 */
class AvisoPagoFallido extends Notification implements ShouldQueue
{
    use Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    public static function de(string $empresa, string $plan, string $url): self
    {
        return new self("No pudimos cobrar el plan {$plan} de {$empresa}. Actualiza tu tarjeta en «Gestionar suscripción».", $url);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{texto: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['texto' => $this->texto, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('No pudimos cobrar tu plan de TalentCloud AI')
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line('Volveremos a intentar el cobro en los próximos días. Si no se completa, tu empresa pasará al plan Gratis: conservarás todo lo que tengas, pero no podrás sumar por encima de sus límites.')
            ->action('Ir a Mi empresa', $this->url);
    }
}
