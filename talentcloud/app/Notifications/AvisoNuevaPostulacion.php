<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanalesSegunPreferencia;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un postulante se postuló por su cuenta: aviso a quien creó la vacante y a
 * la cuenta de empresa. Lleva solo texto, no modelos (ver DatosDeEntrevista).
 */
class AvisoNuevaPostulacion extends Notification implements ShouldQueue
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
        return new self("{$candidato} postuló a {$vacante}.", $url, $candidatoId);
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
            ->subject('Nueva postulación en TalentCloud AI')
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto))
            ->line('Revisa su CV y avanza su postulación en el tablero de selección.')
            ->action('Ver la postulación', $this->url);
    }
}
