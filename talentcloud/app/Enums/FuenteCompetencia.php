<?php

namespace App\Enums;

/** Origen de una competencia del candidato. */
enum FuenteCompetencia: string
{
    use EsEnumDeEstado;

    case Manual = 'manual';
    case Extraccion = 'extraccion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Manual => 'Registrada manualmente',
            self::Extraccion => 'Extraída del CV por IA',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}
