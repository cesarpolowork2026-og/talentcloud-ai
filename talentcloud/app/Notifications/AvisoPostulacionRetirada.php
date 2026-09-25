<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanalesSegunPreferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El postulante retiró su postulación: aviso a quien creó la vacante y a
 * la cuenta de empresa. Lleva solo texto, no modelos (ver DatosDeEntrevista).
 */
class AvisoPostulacionRetirada extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $url,
        public readonly ?int $candidatoId = null,
    ) {
        $this->afterCommit();
    }

    public static function de(string $candidato, string $vacante, string $url, ?int $candidatoId = null): self
    {
        return new self("{$candidato} retiró su postulación a {$vacante}.", $url, $candidatoId);
    }

    /**
     * @return array{texto: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        // candidato_id: EliminarCuentaDePostulante borra los avisos que lo nombran.
        return ['texto' => $this->texto, 'url' => $this->url, 'candidato_id' => $this->candidatoId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Una postulación se retiró en TalentCloud AI')
            ->greeting('¡Hola!')
            ->line($this->texto)
            ->line('Sigue en la columna Descartado del Kanban, marcada como retirada.')
            ->action('Ver el Kanban', $this->url);
    }
}
