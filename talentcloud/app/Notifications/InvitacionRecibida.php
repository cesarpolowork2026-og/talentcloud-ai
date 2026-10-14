<?php

namespace App\Notifications;

use App\Models\Empresa;
use App\Models\Invitacion;
use App\Support\TextoDeCorreo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo con el enlace para unirse al equipo de una empresa. */
class InvitacionRecibida extends Notification
{
    public function __construct(
        public readonly Empresa $empresa,
        public readonly Invitacion $invitacion,
        #[\SensitiveParameter] public readonly string $token,
        public readonly ?string $invitador,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quien = $this->invitador ? TextoDeCorreo::literal($this->invitador).' te invitó' : 'Te invitaron';

        return (new MailMessage)
            ->subject('Únete a '.$this->empresa->nombre.' en TalentCloud AI')
            ->greeting($this->invitacion->nombre ? '¡Hola, '.TextoDeCorreo::literal($this->invitacion->nombre).'!' : '¡Hola!')
            ->line($quien.' a unirte a '.TextoDeCorreo::literal($this->empresa->nombre).' en TalentCloud AI como '.mb_strtolower($this->invitacion->rol->etiquetaEnEmpresa()).'.')
            ->action('Aceptar invitación', route('invitacion.aceptar', $this->token))
            ->line('La invitación vence el '.$this->invitacion->expira_at->translatedFormat('j \d\e F \a \l\a\s H:i').'.')
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
