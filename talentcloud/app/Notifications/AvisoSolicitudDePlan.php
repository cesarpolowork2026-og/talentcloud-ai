<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanalesSegunPreferencia;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al admin SaaS: una empresa pidió otro plan. Lleva solo texto, no modelos. */
class AvisoSolicitudDePlan extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    public static function de(string $empresa, string $planActual, string $planSolicitado, string $solicitante, string $url): self
    {
        return new self("{$solicitante} ({$empresa}) solicitó pasar del plan {$planActual} al plan {$planSolicitado}.", $url);
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
            ->subject('Solicitud de cambio de plan')
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line('Coordina el pago con la empresa y aplica el plan con «Cambiar plan» en Empresas.')
            ->action('Ver empresas', $this->url);
    }
}
