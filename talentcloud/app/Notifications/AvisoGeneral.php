<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso de texto para la campana de notificaciones ("Nueva nota en la
 * postulación de Elena Ruiz."). Las notificaciones de cada módulo
 * guardan también su texto en data['texto'].
 */
class AvisoGeneral extends Notification
{
    use Queueable;

    public function __construct(public readonly string $texto) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{texto: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['texto' => $this->texto];
    }
}
