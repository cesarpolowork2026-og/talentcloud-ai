<?php

namespace App\Enums;

/** Estado de una vacante. */
enum EstadoVacante: string
{
    use EsEnumDeEstado;

    case Borrador = 'borrador';
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Abierta => 'Abierta',
            self::EnRevision => 'En revisión',
            self::Cerrada => 'Cerrada',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'neutral',
            self::Abierta => 'accent',
            self::EnRevision => 'neutral',
            self::Cerrada => 'outline',
        };
    }
}
