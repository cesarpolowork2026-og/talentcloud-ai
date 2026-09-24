<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanalesSegunPreferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La IA terminó de analizar un CV: aviso a quien lo subió (el reclutador o el
 * propio postulante). Lleva solo texto, no modelos (ver DatosDeEntrevista).
 */
class AvisoCvAnalizado extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly string $texto,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    public static function paraReclutador(string $candidato, int $competencias, string $url): self
    {
        return new self("Nuevo CV analizado de {$candidato}: ".self::competencias($competencias).'.', $url);
    }

    public static function paraPostulante(int $competencias, string $url): self
    {
        return new self('Tu CV fue analizado: '.self::competencias($competencias).'.', $url);
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
            ->subject('CV analizado por la IA')
            ->greeting('¡Hola!')
            ->line($this->texto)
            ->action('Ver el detalle', $this->url);
    }

    private static function competencias(int $cantidad): string
    {
        return $cantidad === 1 ? '1 competencia detectada' : "{$cantidad} competencias detectadas";
    }
}
