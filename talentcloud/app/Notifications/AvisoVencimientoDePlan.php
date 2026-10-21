<?php

namespace App\Notifications;

use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a la cuenta de empresa: su plan asignado a mano está por vencer o ya venció. Llega siempre por correo, aunque
 * haya desactivado los avisos: de no renovarlo, la empresa pasa al plan Gratis. Lleva solo texto, no modelos.
 */
class AvisoVencimientoDePlan extends Notification implements ShouldQueue
{
    use Queueable;

    private function __construct(
        public readonly string $asunto,
        public readonly string $texto,
        public readonly string $consecuencia,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    /** `$dias`: días que quedan contando el de hoy; `$fecha`: ya formateada. */
    public static function porVencer(string $empresa, string $plan, string $fecha, int $dias, string $url): self
    {
        $quedan = $dias === 1 ? 'queda 1 día' : "quedan {$dias} días";

        return new self(
            'Tu plan de TalentCloud AI vence pronto',
            "El plan {$plan} de {$empresa} vence el {$fecha} ({$quedan}).",
            'Si no se renueva, tu empresa pasará al plan Gratis: conservarás todo lo que tengas, pero no podrás sumar por encima de sus límites. Escríbenos para coordinar la renovación.',
            $url,
        );
    }

    public static function vencido(string $empresa, string $plan, string $fecha, string $url): self
    {
        return new self(
            'Tu plan de TalentCloud AI venció',
            "El plan {$plan} de {$empresa} venció el {$fecha} y ahora rige el plan Gratis.",
            'Conservas todo lo que tengas, pero no podrás sumar por encima de los límites de Gratis. Escríbenos para renovar tu plan y recuperarlos.',
            $url,
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
     * @return array{texto: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['texto' => $this->texto, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->asunto)
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line($this->consecuencia)
            ->action('Ir a Mi empresa', $this->url);
    }
}
