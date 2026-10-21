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

    /** `$vence`: fecha ya formateada hasta la que rige el plan, si lo asignó el admin con vencimiento. */
    public static function de(string $empresa, string $plan, string $limites, string $url, ?string $vence = null): self
    {
        return new self("{$empresa} pasó al plan {$plan}.".self::vigencia($vence), $limites, $url);
    }

    /** El mismo plan con una nueva fecha de vencimiento (o sin ella). */
    public static function renovado(string $empresa, string $plan, string $limites, string $url, ?string $vence = null): self
    {
        return new self("El plan {$plan} de {$empresa} se renovó.".self::vigencia($vence), $limites, $url);
    }

    private static function vigencia(?string $vence): string
    {
        return $vence === null ? '' : " Vigente hasta el {$vence}.";
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
