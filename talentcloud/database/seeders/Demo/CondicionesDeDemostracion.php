<?php

namespace Database\Seeders\Demo;

use App\Models\Vacante;
use App\Support\BolsaPublica;

/**
 * Jornada y rango de sueldo (en soles al mes) de algunas vacantes de la demostración, para que la bolsa de empleos
 * muestre las dos situaciones reales: vacantes con condiciones y vacantes en las que la empresa no las indicó.
 * Son valores inventados para la demostración. Es idempotente: no toca una vacante que ya tiene jornada.
 */
final class CondicionesDeDemostracion
{
    /** título => [jornada, mínimo, máximo] */
    private const POR_TITULO = [
        'Desarrollador Backend' => ['completa', 7000, 10000],
        'DevOps Engineer' => ['completa', 8000, 12000],
        'Data Analyst' => ['completa', 5000, 7500],
        'Product Designer' => ['completa', 5500, null],
        'QA Engineer' => ['completa', 4500, 6500],
        'Barista' => ['completa', 1500, 2200],
        'Asesor(a) de Tienda' => ['completa', 1200, 1800],
        'Cajero(a)' => ['parcial', 1100, null],
    ];

    public static function aplicar(): void
    {
        foreach (self::POR_TITULO as $titulo => [$jornada, $minimo, $maximo]) {
            Vacante::sinFiltroDeEmpresa()
                ->where('titulo', $titulo)
                ->whereNull('jornada')
                ->update(['jornada' => $jornada, 'sueldo_minimo' => $minimo, 'sueldo_maximo' => $maximo]);
        }

        BolsaPublica::olvidar();
    }
}
