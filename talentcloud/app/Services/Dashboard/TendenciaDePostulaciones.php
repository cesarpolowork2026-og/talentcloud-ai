<?php

namespace App\Services\Dashboard;

use App\DTO\TendenciaDePostulacionesData;
use App\Models\Postulacion;
use App\Support\CacheDeResumenes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Evolución de las postulaciones recibidas: el periodo (los últimos `$dias` días completos, hoy incluido) frente
 * al anterior de la misma duración. Con el alcance de empresa vigente (el admin SaaS ve toda la plataforma).
 * Una sola consulta agregada; las fechas son días del calendario en la zona horaria de la aplicación.
 */
class TendenciaDePostulaciones
{
    /** Periodos que ofrece el Dashboard. */
    public const PERIODOS = [7, 30, 90];

    /** Puntos que se dibujan como máximo: un periodo largo se agrupa en bloques de varios días. */
    private const PUNTOS_MAXIMOS = 30;

    public function calcular(int $dias, ?int $vacanteId = null): TendenciaDePostulacionesData
    {
        return CacheDeResumenes::recordar("tendencia-{$dias}-".($vacanteId ?? 'todas'), fn () => $this->leer($dias, $vacanteId));
    }

    private function leer(int $dias, ?int $vacanteId): TendenciaDePostulacionesData
    {
        $hoy = Carbon::today();
        $desde = $hoy->copy()->subDays($dias - 1);
        $desdeAnterior = $desde->copy()->subDays($dias);
        $porCubeta = max(1, (int) ceil($dias / self::PUNTOS_MAXIMOS));
        $cantidad = (int) ceil($dias / $porCubeta);

        $filas = Postulacion::query()
            ->where('created_at', '>=', $desdeAnterior)
            ->where('created_at', '<', $hoy->copy()->addDay())
            ->when($vacanteId !== null, fn (Builder $q) => $q->where('vacante_id', $vacanteId))
            ->toBase()
            ->selectRaw(
                '(created_at >= ?) AS actual,
                FLOOR((created_at::date - (CASE WHEN created_at >= ? THEN ?::date ELSE ?::date END)) / ?::int) AS cubeta,
                COUNT(*) AS n',
                [$desde, $desde, $desde->toDateString(), $desdeAnterior->toDateString(), $porCubeta],
            )
            ->groupByRaw('1, 2')
            ->get();

        $conteo = ['actual' => array_fill(0, $cantidad, 0), 'anterior' => array_fill(0, $cantidad, 0)];
        foreach ($filas as $fila) {
            $indice = (int) $fila->cubeta;

            if ($indice >= 0 && $indice < $cantidad) {
                $conteo[$fila->actual ? 'actual' : 'anterior'][$indice] = (int) $fila->n;
            }
        }

        $cubetas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $inicio = $desde->copy()->addDays($i * $porCubeta);

            $cubetas[] = [
                'desde' => $inicio,
                'hasta' => $inicio->copy()->addDays($porCubeta - 1)->min($hoy),
                'actual' => $conteo['actual'][$i],
                'anterior' => $conteo['anterior'][$i],
            ];
        }

        return new TendenciaDePostulacionesData($dias, $porCubeta, $desde, $hoy, $desdeAnterior, $desde->copy()->subDay(), $cubetas);
    }
}
