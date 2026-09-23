<?php

namespace App\Enums;

enum EstadoEmpresa: string
{
    use EsEnumDeEstado;

    case Activa = 'activa';
    case Suspendida = 'suspendida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Activa => 'accent',
            self::Suspendida => 'outline',
        };
    }
}
