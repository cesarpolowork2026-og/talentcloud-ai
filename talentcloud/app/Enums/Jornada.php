<?php

namespace App\Enums;

/** Jornada de trabajo de una vacante (opcional). */
enum Jornada: string
{
    use EsEnumDeEstado;

    case Completa = 'completa';
    case Parcial = 'parcial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Completa => 'Tiempo completo',
            self::Parcial => 'Medio tiempo',
        };
    }

    /** El valor de `employmentType` de schema.org (Google for Jobs). */
    public function tipoDeEmpleo(): string
    {
        return match ($this) {
            self::Completa => 'FULL_TIME',
            self::Parcial => 'PART_TIME',
        };
    }
}
