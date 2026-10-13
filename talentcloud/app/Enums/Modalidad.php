<?php

namespace App\Enums;

/** Modalidad de trabajo de una vacante. */
enum Modalidad: string
{
    use EsEnumDeEstado;

    case Remoto = 'remoto';
    case Presencial = 'presencial';
    case Hibrido = 'hibrido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Remoto => 'Remoto',
            self::Presencial => 'Presencial',
            self::Hibrido => 'Híbrido',
        };
    }

    /** Presencial e híbrido tienen un lugar de trabajo; en remoto solo puede haber una cobertura opcional. */
    public function requiereUbicacion(): bool
    {
        return $this !== self::Remoto;
    }

    /**
     * Lo que se guarda en `vacantes.ubicacion`: en presencial e híbrido, el lugar; en
     * remoto, la cobertura opcional ("Perú", "LATAM"), nunca la palabra "Remoto".
     */
    public function normalizarUbicacion(?string $ubicacion): ?string
    {
        $ubicacion = trim((string) preg_replace('/\s+/', ' ', (string) $ubicacion));

        if ($ubicacion === '' || ($this === self::Remoto && mb_strtolower($ubicacion) === mb_strtolower($this->etiqueta()))) {
            return null;
        }

        return $ubicacion;
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}
