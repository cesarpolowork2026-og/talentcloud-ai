<?php

namespace App\Enums;

/**
 * Para enums de estados con etiqueta y tono: se muestran con
 * <x-ui.estado :valor="$modelo->estado" /> y alimentan selectores y filtros.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait EsEnumDeEstado
{
    abstract public function etiqueta(): string;

    /** @return array<string, string> valor => etiqueta, en el orden declarado. */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }
}
