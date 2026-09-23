<?php

namespace App\Enums;

/** Motor que calculó un matching. */
enum MotorMatching: string
{
    use EsEnumDeEstado;

    case Http = 'http';
    case Fake = 'fake';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Http => 'Servicio de IA',
            self::Fake => 'Análisis básico',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}
