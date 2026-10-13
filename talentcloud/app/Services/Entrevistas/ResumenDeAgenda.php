<?php

namespace App\Services\Entrevistas;

use App\Enums\EstadoEntrevista;
use App\Models\Entrevista;

/**
 * Cuántas entrevistas hay hoy, en los próximos días y por confirmar, con el alcance de la
 * empresa vigente. Una sola lectura: la usan la agenda y el dashboard.
 */
class ResumenDeAgenda
{
    /** Días que cuentan como «próximos». */
    public const DIAS_PROXIMOS = 7;

    /**
     * @return object{hoy: int, proximas: int, sin_confirmar: int}
     */
    public function calcular(): object
    {
        $cifras = Entrevista::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) FILTER (WHERE inicia_at BETWEEN ? AND ? AND estado <> ?) AS hoy,
                COUNT(*) FILTER (WHERE inicia_at >= ? AND inicia_at < ? AND estado IN (?, ?)) AS proximas,
                COUNT(*) FILTER (WHERE inicia_at >= ? AND estado = ?) AS sin_confirmar',
                [
                    now()->startOfDay(), now()->endOfDay(), EstadoEntrevista::Cancelada->value,
                    now(), now()->addDays(self::DIAS_PROXIMOS), EstadoEntrevista::Pendiente->value, EstadoEntrevista::Confirmada->value,
                    now(), EstadoEntrevista::Pendiente->value,
                ],
            )
            ->first();

        return (object) [
            'hoy' => (int) ($cifras->hoy ?? 0),
            'proximas' => (int) ($cifras->proximas ?? 0),
            'sin_confirmar' => (int) ($cifras->sin_confirmar ?? 0),
        ];
    }
}
