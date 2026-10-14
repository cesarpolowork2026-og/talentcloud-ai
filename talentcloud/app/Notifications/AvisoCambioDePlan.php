<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanalesSegunPreferencia;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso a la cuenta de empresa de que cambió su plan. Lleva solo texto, no modelos. */
class AvisoCambioDePlan extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $limites,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    public static function de(string $empresa, string $plan, string $limites, string $url): self
    {
        return new self("{$empresa} pasó al plan {$plan}.", $limites, $url);
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
            ->subject('Tu plan de TalentCloud AI cambió')
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line(TextoDeCorreo::literal($this->limites))
            ->action('Ver mi empresa', $this->url);
    }
}
