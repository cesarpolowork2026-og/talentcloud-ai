<?php

namespace App\Notifications;

use App\DTO\DatosDeEntrevista;
use App\Notifications\Concerns\CanalesSegunPreferencia;
use App\Support\EnlaceExterno;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Una entrevista cambió de fecha, para el entrevistador o para el candidato.
 * Un solo aviso (antes había que cancelar y programar otra: dos avisos).
 */
class AvisoEntrevistaReprogramada extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly DatosDeEntrevista $datos,
        public readonly string $fechaAnterior,
        public readonly bool $paraCandidato,
    ) {
        $this->afterCommit();
    }

    public static function paraEntrevistador(DatosDeEntrevista $datos, string $fechaAnterior): self
    {
        return new self($datos, $fechaAnterior, paraCandidato: false);
    }

    public static function paraCandidato(DatosDeEntrevista $datos, string $fechaAnterior): self
    {
        return new self($datos, $fechaAnterior, paraCandidato: true);
    }

    public function texto(): string
    {
        $d = $this->datos;

        return $this->paraCandidato
            ? "{$d->empresa} cambió tu entrevista para {$d->vacante}: ahora es el {$d->fecha} (antes, el {$this->fechaAnterior})."
            : "La entrevista con {$d->candidato} pasó del {$this->fechaAnterior} al {$d->fecha}.";
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
            ->subject('Entrevista reprogramada: '.$this->datos->vacante)
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto()))
            ->line("Duración: {$this->datos->duracionMin} min · {$this->datos->modalidad}.");

        if ($this->datos->lugar !== null) {
            $mensaje->line(TextoDeCorreo::literal($this->datos->lugar));
        }

        if (($enlace = EnlaceExterno::seguro($this->datos->enlace)) !== null) {
            return $mensaje->action('Unirse a la videollamada', $enlace);
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
