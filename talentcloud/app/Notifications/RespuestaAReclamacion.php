<?php

namespace App\Notifications;

use App\Models\Reclamacion;
use App\Support\Formato;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Respuesta del proveedor a una hoja del Libro de Reclamaciones, por correo al consumidor. Solo texto. */
class RespuestaAReclamacion extends Notification implements ShouldQueue
{
    use Queueable;

    private function __construct(
        public readonly string $codigo,
        public readonly string $presentada,
        public readonly string $respuesta,
    ) {
        $this->afterCommit();
    }

    public static function de(Reclamacion $reclamacion): self
    {
        return new self($reclamacion->codigo(), Formato::fechaLarga($reclamacion->created_at), (string) $reclamacion->respuesta);
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
        $mensaje = (new MailMessage)
            ->subject('Respuesta a tu hoja '.$this->codigo.' del Libro de Reclamaciones')
            ->greeting('¡Hola!')
            ->line("Esta es nuestra respuesta a la hoja {$this->codigo}, que presentaste el {$this->presentada}:");

        // Un párrafo por línea de la respuesta.
        foreach (preg_split('/\R+/', $this->respuesta) ?: [] as $parrafo) {
            if (trim($parrafo) !== '') {
                $mensaje->line(trim($parrafo));
            }
        }

        return $mensaje->line('Si no estás conforme, puedes acudir al INDECOPI.');
    }
}
