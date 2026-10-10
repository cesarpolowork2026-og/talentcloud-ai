<?php

namespace App\Enums;

/** Documento de identidad del consumidor en el Libro de Reclamaciones. */
enum TipoDocumento: string
{
    use EsEnumDeEstado;

    case Dni = 'dni';
    case CarneExtranjeria = 'ce';
    case Pasaporte = 'pasaporte';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::CarneExtranjeria => 'Carné de extranjería',
            self::Pasaporte => 'Pasaporte',
        };
    }

    /** Formato del número: DNI de 8 dígitos; los demás, de 6 a 12 letras o dígitos. */
    public function patron(): string
    {
        return match ($this) {
            self::Dni => '/^\d{8}$/',
            self::CarneExtranjeria, self::Pasaporte => '/^[A-Za-z0-9]{6,12}$/',
        };
    }
}
