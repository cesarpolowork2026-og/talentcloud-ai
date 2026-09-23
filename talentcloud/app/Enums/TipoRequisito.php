<?php

namespace App\Enums;

/** Peso cualitativo de un requisito de la vacante. */
enum TipoRequisito: string
{
    use EsEnumDeEstado;

    case Obligatorio = 'obligatorio';
    case Deseable = 'deseable';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Obligatorio => 'Obligatorio',
            self::Deseable => 'Deseable',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Obligatorio => 'outline',
            self::Deseable => 'neutral',
        };
    }
}
