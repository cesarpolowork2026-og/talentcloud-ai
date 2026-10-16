<?php

namespace App\Enums;

/** Resultado de un requisito en el matching (pantalla "Matching inteligente" del prototipo). */
enum NivelCoincidencia: string
{
    use EsEnumDeEstado;

    case Coincide = 'coincide';
    case Revisar = 'revisar';
    case NoEncontrado = 'no_encontrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Coincide => 'Coincide',
            self::Revisar => 'Revisar',
            self::NoEncontrado => 'No encontrado',
        };
    }

    /** Tonos del prototipo: Coincide = accent, Revisar = neutral, No encontrado = accent-2. */
    public function tono(): string
    {
        return match ($this) {
            self::Coincide => 'accent',
            self::Revisar => 'neutral',
            self::NoEncontrado => 'accent-2',
        };
    }
}
