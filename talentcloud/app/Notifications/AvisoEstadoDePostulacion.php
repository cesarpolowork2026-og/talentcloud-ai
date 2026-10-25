<?php

namespace App\Notifications;

use App\Enums\EstadoDeMiPostulacion;
use App\Notifications\Concerns\CanalesSegunPreferencia;
use App\Support\TextoDeCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cambio en el estado de una postulación, para el candidato. Textos amables y
 * sin datos internos (ni puntajes, ni notas, ni la etapa del Kanban). Lleva
 * solo texto, no modelos (ver DatosDeEntrevista).
 */
class AvisoEstadoDePostulacion extends Notification implements ShouldQueue
{
    use CanalesSegunPreferencia, Queueable;

    private function __construct(
        public readonly string $asunto,
        public readonly string $texto,
        public readonly ?string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @param  string|null  $url  "Mis postulaciones", solo si el candidato tiene cuenta.
     * @param  EstadoDeMiPostulacion|null  $anterior  lo último que se le avisó (null: es el primer aviso de esta postulación).
     */
    public static function de(EstadoDeMiPostulacion $estado, string $vacante, string $empresa, ?string $url, ?EstadoDeMiPostulacion $anterior = null): self
    {
        [$asunto, $texto] = match ($estado) {
            EstadoDeMiPostulacion::Postulado => $anterior === null
                ? ["{$empresa} te registró como postulante a {$vacante}", "{$empresa} te registró como postulante a {$vacante}. Puedes seguir tu proceso en «Mis postulaciones»."]
                : ["Tu postulación a {$vacante} volvió al inicio del proceso", "Tu postulación a {$vacante} en {$empresa} volvió a la etapa «Postulado»: sigues en el proceso."],
            EstadoDeMiPostulacion::Entrevista => [
                "Avanzaste a la etapa de entrevistas de {$vacante}",
                "Avanzaste a la etapa de entrevistas de {$vacante}. {$empresa} se pondrá en contacto contigo para coordinarla.",
            ],
            EstadoDeMiPostulacion::Oferta => [
                "{$empresa} te hizo una oferta para {$vacante}",
                "¡Buenas noticias! {$empresa} te hizo una oferta para {$vacante}. Pronto se pondrá en contacto contigo para conversar los detalles.",
            ],
            EstadoDeMiPostulacion::EnRevision => [
                "Tu postulación a {$vacante} está en revisión",
                "{$empresa} está revisando tu postulación a {$vacante}.",
            ],
            EstadoDeMiPostulacion::Contratado => [
                "¡Felicitaciones! {$empresa} eligió tu candidatura",
                "¡Felicitaciones! {$empresa} eligió tu candidatura para {$vacante}. Pronto se pondrán en contacto contigo.",
            ],
            EstadoDeMiPostulacion::NoSeleccionado => [
                "Novedades sobre tu postulación a {$vacante}",
                "{$empresa} decidió continuar con otros perfiles para {$vacante}. Gracias por tu interés y mucho éxito.",
            ],
            EstadoDeMiPostulacion::Cerrada => [
                "El proceso de {$vacante} se cerró",
                "El proceso de {$vacante} en {$empresa} se cerró. Gracias por postular.",
            ],
            default => ["Novedades sobre tu postulación a {$vacante}", "Tu postulación a {$vacante} en {$empresa} tiene novedades."],
        };

        return new self($asunto, $texto, $url);
    }

    /**
     * @return array{texto: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return ['texto' => $this->texto, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->asunto)
            ->greeting('¡Hola!')
            ->line(TextoDeCorreo::literal($this->texto));

        return $this->url === null ? $mensaje : $mensaje->action('Ver mis postulaciones', $this->url);
    }
}
