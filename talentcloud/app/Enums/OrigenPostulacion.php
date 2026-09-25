<?php

namespace App\Enums;

/** Quién creó la postulación: el equipo de la empresa o el propio postulante. */
enum OrigenPostulacion: string
{
    case Equipo = 'equipo';
    case Postulante = 'postulante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Equipo => 'Registradas por el equipo',
            self::Postulante => 'Postulaciones propias',
        };
    }
}
