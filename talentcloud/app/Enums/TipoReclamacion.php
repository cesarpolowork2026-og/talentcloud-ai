<?php

namespace App\Enums;

/** Tipo de hoja del Libro de Reclamaciones, con la definición del reglamento. */
enum TipoReclamacion: string
{
    use EsEnumDeEstado;

    case Reclamo = 'reclamo';
    case Queja = 'queja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Reclamo => 'Reclamo',
            self::Queja => 'Queja',
        };
    }

    public function definicion(): string
    {
        return match ($this) {
            self::Reclamo => 'Disconformidad con el servicio contratado.',
            self::Queja => 'Malestar con la atención recibida, no relacionado con el servicio.',
        };
    }
}
