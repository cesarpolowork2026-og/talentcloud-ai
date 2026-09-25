<?php

namespace App\Notifications;

use App\DTO\DatosDeEntrevista;
use App\Notifications\Concerns\CanalesSegunPreferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Entrevista programada o cancelada, para el entrevistador o para el candidato. */
class AvisoDeEntrevista extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly DatosDeEntrevista $datos,
        public readonly bool $cancelada,
        public readonly bool $paraCandidato,
    ) {
        $this->afterCommit();
    }

    public static function programadaParaEntrevistador(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: false, paraCandidato: false);
    }

    public static function programadaParaCandidato(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: false, paraCandidato: true);
    }

    public static function canceladaParaEntrevistador(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: true, paraCandidato: false);
    }

    public static function canceladaParaCandidato(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: true, paraCandidato: true);
    }

    public function texto(): string
    {
        $d = $this->datos;

        return match ([$this->cancelada, $this->paraCandidato]) {
            [false, false] => "Entrevista con {$d->candidato} para {$d->vacante} el {$d->fecha}.",
            [false, true] => "Tienes una entrevista para {$d->vacante} en {$d->empresa} el {$d->fecha}.",
            [true, false] => "Se canceló la entrevista con {$d->candidato} del {$d->fecha}.",
            [true, true] => "{$d->empresa} canceló tu entrevista para {$d->vacante} del {$d->fecha}.",
        };
    }

    /**
     * @return array{texto: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        // candidato_id: EliminarCuentaDePostulante borra los avisos que lo nombran.
        return ['texto' => $this->texto(), 'url' => $this->url(), 'candidato_id' => $this->datos->candidatoId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject(($this->cancelada ? 'Entrevista cancelada: ' : 'Entrevista programada: ').$this->datos->vacante)
            ->greeting('¡Hola!')
            ->line($this->texto());

        if (! $this->cancelada) {
            $mensaje->line("Duración: {$this->datos->duracionMin} min · {$this->datos->modalidad}.");
        }

        if (! $this->cancelada && $this->datos->enlace !== null) {
            return $mensaje->action('Unirse a la videollamada', $this->datos->enlace);
        }

        // Un candidato sin cuenta no tiene a dónde entrar.
        return $notifiable instanceof AnonymousNotifiable
            ? $mensaje
            : $mensaje->action($this->paraCandidato ? 'Ver mis postulaciones' : 'Ver entrevistas', $this->url());
    }

    private function url(): string
    {
        return $this->paraCandidato ? route('postulante.postulaciones') : route('entrevistas.index');
    }
}
