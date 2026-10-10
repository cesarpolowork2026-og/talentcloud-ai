<?php

namespace App\Notifications;

use App\Models\Reclamacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Copia de una hoja del Libro de Reclamaciones por correo: al correo de soporte
 * (para atenderla) y al consumidor (constancia de lo que presentó). Lleva solo
 * texto, no el modelo.
 */
class HojaDeReclamacion extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $hoja
     * @param  list<string>  $avisos
     */
    private function __construct(
        public readonly string $asunto,
        public readonly string $introduccion,
        public readonly array $hoja,
        public readonly array $avisos,
        public readonly ?string $url,
    ) {
        $this->afterCommit();
    }

    public static function paraSoporte(Reclamacion $reclamacion, string $url): self
    {
        return new self(
            'Nueva hoja '.$reclamacion->codigo().' del Libro de Reclamaciones',
            'Se presentó un '.mb_strtolower($reclamacion->tipo->etiqueta()).' en el Libro de Reclamaciones de TalentCloud AI.',
            $reclamacion->hoja(),
            ['Regístrala respondida en la aplicación antes de la fecha límite: así queda constancia y el consumidor recibe la respuesta por correo.'],
            $url,
        );
    }

    public static function paraConsumidor(Reclamacion $reclamacion): self
    {
        return new self(
            'Tu hoja '.$reclamacion->codigo().' del Libro de Reclamaciones',
            'Recibimos tu '.mb_strtolower($reclamacion->tipo->etiqueta()).'. Esta es la copia de la hoja que presentaste.',
            $reclamacion->hoja(),
            [
                'Te responderemos a este correo a más tardar el día indicado como fecha límite.',
                'La presentación de un reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para presentar una denuncia ante el INDECOPI.',
            ],
            null,
        );
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
            ->subject($this->asunto)
            ->greeting('¡Hola!')
            ->line($this->introduccion);

        foreach ($this->hoja as $etiqueta => $valor) {
            $mensaje->line($etiqueta.': '.self::literal($valor));
        }

        foreach ($this->avisos as $aviso) {
            $mensaje->line($aviso);
        }

        return $this->url !== null ? $mensaje->action('Atender en TalentCloud', $this->url) : $mensaje;
    }

    /**
     * Texto del consumidor como literal: las líneas del correo se interpretan como Markdown y
     * "[Pulsa aquí](https://…)" llegaría como un enlace disfrazado, enviado desde nuestra
     * cuenta a cualquier dirección que se escriba en la hoja. Se escapan los signos de
     * Markdown (el HTML ya lo escapa la plantilla, por eso no se tocan <, > ni &).
     */
    public static function literal(string $texto): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|])/', '\\\\$1', $texto);
    }
}
