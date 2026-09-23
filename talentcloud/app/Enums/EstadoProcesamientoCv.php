<?php

namespace App\Enums;

/** Estado de la extracción y el análisis de un CV. */
enum EstadoProcesamientoCv: string
{
    use EsEnumDeEstado;

    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Procesado = 'procesado';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Procesando => 'Procesando',
            self::Procesado => 'Procesado',
            self::Error => 'Error al analizar el CV',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'neutral',
            self::Procesando => 'neutral',
            self::Procesado => 'accent',
            self::Error => 'accent-2',
        };
    }
}
