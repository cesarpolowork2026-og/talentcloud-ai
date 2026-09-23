<?php

namespace App\Enums;

/** Estado de una evaluación. */
enum EstadoEvaluacion: string
{
    use EsEnumDeEstado;

    case Pendiente = 'pendiente';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'neutral',
            self::EnRevision => 'neutral',
            self::Aprobado => 'accent',
            self::Rechazado => 'neutral',
        };
    }
}
