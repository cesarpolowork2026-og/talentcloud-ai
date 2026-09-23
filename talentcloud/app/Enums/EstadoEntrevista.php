<?php

namespace App\Enums;

/** Estado de una entrevista. */
enum EstadoEntrevista: string
{
    use EsEnumDeEstado;

    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'neutral',
            self::Confirmada => 'accent',
            self::Completada => 'accent',
            self::Cancelada => 'outline',
        };
    }
}
