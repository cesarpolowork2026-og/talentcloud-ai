<?php

namespace App\Enums;

/** Estado de una hoja del Libro de Reclamaciones. «Vencida» no es un estado: es pendiente fuera de plazo. */
enum EstadoReclamacion: string
{
    use EsEnumDeEstado;

    case Pendiente = 'pendiente';
    case Respondida = 'respondida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Respondida => 'Respondida',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'accent-2',
            self::Respondida => 'accent',
        };
    }
}
