<?php

namespace App\Enums;

/** Cómo se realiza una entrevista. */
enum ModalidadEntrevista: string
{
    use EsEnumDeEstado;

    case Videollamada = 'videollamada';
    case Presencial = 'presencial';
    case Telefonica = 'telefonica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Videollamada => 'Videollamada',
            self::Presencial => 'Presencial',
            self::Telefonica => 'Telefónica',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}
