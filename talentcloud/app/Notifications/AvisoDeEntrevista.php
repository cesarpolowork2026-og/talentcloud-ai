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

/** Entrevista programada o cancelada (para el entrevistador o el candidato), y confirmada o realizada (para el candidato). */
class AvisoDeEntrevista extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    /**
     * null, 'confirmada' o 'realizada': avisos al candidato sobre una entrevista que ya conocía. Propiedad con valor
     * por defecto (no promovida): un aviso que ya estaba en la cola antes de existir este campo sigue funcionando.
     */
    public ?string $cambio = null;

    private function __construct(
        public readonly DatosDeEntrevista $datos,
        public readonly bool $cancelada,
        public readonly bool $paraCandidato,
        ?string $cambio = null,
    ) {
        $this->cambio = $cambio;
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

    public static function confirmadaParaCandidato(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: false, paraCandidato: true, cambio: 'confirmada');
    }

    public static function realizadaParaCandidato(DatosDeEntrevista $datos): self
    {
        return new self($datos, cancelada: false, paraCandidato: true, cambio: 'realizada');
    }

    public function texto(): string
    {
        $d = $this->datos;

        if ($this->cambio === 'confirmada') {
            return "{$d->empresa} confirmó tu entrevista para {$d->vacante} el {$d->fecha}.";
        }

        if ($this->cambio === 'realizada') {
            return "{$d->empresa} registró como realizada tu entrevista para {$d->vacante}. Te avisaremos cuando haya novedades.";
        }

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
            ->subject(match (true) {
                $this->cancelada => 'Entrevista cancelada: ',
                $this->cambio === 'confirmada' => 'Entrevista confirmada: ',
                $this->cambio === 'realizada' => 'Entrevista realizada: ',
                default => 'Entrevista programada: ',
            }.$this->datos->vacante)
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto()));

        if (! $this->cancelada && $this->cambio !== 'realizada') {
            $mensaje->line("Duración: {$this->datos->duracionMin} min · {$this->datos->modalidad}.");

            if ($this->datos->lugar !== null) {
                $mensaje->line(TextoDeCorreo::literal($this->datos->lugar));
            }
        }

        if (! $this->cancelada && $this->cambio !== 'realizada' && ($enlace = EnlaceExterno::seguro($this->datos->enlace)) !== null) {
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
