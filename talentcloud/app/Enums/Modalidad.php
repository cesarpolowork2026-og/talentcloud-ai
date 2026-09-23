<?php

namespace App\Enums;

/** Modalidad de trabajo de una vacante. */
enum Modalidad: string
{
    use EsEnumDeEstado;

    case Remoto = 'remoto';
    case Presencial = 'presencial';
    case Hibrido = 'hibrido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Remoto => 'Remoto',
            self::Presencial => 'Presencial',
            self::Hibrido => 'Híbrido',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}
