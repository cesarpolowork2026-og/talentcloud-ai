<?php

namespace App\Services\Dashboard;

use App\DTO\EmbudoDeSeleccionData;
use App\Enums\EtapaPostulacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Embudo de selección del dashboard. Cada consulta pasa por el filtro por
 * empresa: una empresa ve lo suyo y el admin SaaS, toda la plataforma.
 *
 * Una postulación "alcanzó" una etapa si llegó a ella o a una posterior según
 * su historial (postulacion_etapas); un descarte no borra lo alcanzado. Las
 * postulaciones anteriores al historial solo tienen su etapa actual. Las que
 * retiró el propio postulante se cuentan aparte de los descartes.
 */
class EmbudoDeSeleccion
{
    public const DIAS = 90;

    public function calcular(?int $vacanteId = null): EmbudoDeSeleccionData
    {
        $desde = now()->subDays(self::DIAS);

        $postulaciones = Postulacion::query()
            ->where('postulaciones.created_at', '>=', $desde)
            ->when($vacanteId !== null, fn (Builder $q) => $q->where('postulaciones.vacante_id', $vacanteId));

        $total = (clone $postulaciones)->count();
        $etapas = self::etapasDelEmbudo();
        $porRango = $this->postulacionesPorRangoAlcanzado(clone $postulaciones);

        $filas = [];
        foreach ($etapas as $rango => $etapa) {
            $alcanzaron = (int) $porRango->filter(fn (int $n, int $r) => $r >= $rango)->sum();
            $filas[] = [
                'etapa' => $etapa,
                'total' => $alcanzaron,
                'porcentaje' => $total > 0 ? (int) round($alcanzaron * 100 / $total) : 0,
            ];
        }

        [$contrataciones, $diasHastaContratar] = $this->tiempoHastaContratar($desde, $vacanteId);

        return new EmbudoDeSeleccionData(
            dias: self::DIAS,
            total: $total,
            etapas: $filas,
            descartadas: (clone $postulaciones)->where('etapa', EtapaPostulacion::Descartado)->whereNull('retirada_at')->count(),
            retiradas: (clone $postulaciones)->whereNotNull('retirada_at')->count(),
            diasHastaContratar: $diasHastaContratar,
            contrataciones: $contrataciones,
            porOrigen: (clone $postulaciones)->toBase()
                ->selectRaw('origen, COUNT(*) AS total')
                ->groupBy('origen')
                ->pluck('total', 'origen')
                ->map(fn ($n) => (int) $n)
                ->all(),
        );
    }

    /**
     * Etapas del embudo en orden, sin Descartado (que no es un avance).
     *
     * @return list<EtapaPostulacion>
     */
    public static function etapasDelEmbudo(): array
    {
        $etapas = [];
        foreach (EtapaPostulacion::cases() as $etapa) {
            if ($etapa !== EtapaPostulacion::Descartado) {
                $etapas[] = $etapa;
            }
        }

        return $etapas;
    }

    /**
     * Cuántas postulaciones tienen como etapa más avanzada cada rango (0 = Postulado).
     *
     * @param  Builder<Postulacion>  $postulaciones
     * @return Collection<int, int>
     */
    private function postulacionesPorRangoAlcanzado(Builder $postulaciones): Collection
    {
        // Los valores del CASE salen del enum, no de la entrada del usuario.
        $caso = 'CASE etapa';
        foreach (self::etapasDelEmbudo() as $rango => $etapa) {
            $caso .= " WHEN '{$etapa->value}' THEN {$rango}";
        }
        $caso .= ' END';

        $alcance = PostulacionEtapa::query()
            ->select('postulacion_id')
            ->selectRaw("MAX({$caso}) AS rango")
            ->where('etapa', '!=', EtapaPostulacion::Descartado)
            ->groupBy('postulacion_id');

        return $postulaciones
            ->leftJoinSub($alcance, 'alcance', 'alcance.postulacion_id', '=', 'postulaciones.id')
            ->toBase()
            ->selectRaw('COALESCE(alcance.rango, 0) AS rango, COUNT(*) AS total')
            ->groupByRaw('COALESCE(alcance.rango, 0)')
            ->pluck('total', 'rango')
            ->mapWithKeys(fn ($n, $r) => [(int) $r => (int) $n]);
    }

    /**
     * Contrataciones del periodo (por la fecha en que pasaron a Contratado) y
     * los días medios desde la postulación hasta entonces.
     *
     * @return array{0: int, 1: float|null}
     */
    private function tiempoHastaContratar(Carbon $desde, ?int $vacanteId): array
    {
        $segundos = PostulacionEtapa::query()
            ->join('postulaciones', 'postulaciones.id', '=', 'postulacion_etapas.postulacion_id')
            ->where('postulacion_etapas.etapa', EtapaPostulacion::Contratado)
            ->where('postulacion_etapas.desde', '>=', $desde)
            ->when($vacanteId !== null, fn (Builder $q) => $q->where('postulaciones.vacante_id', $vacanteId))
            ->toBase()
            ->selectRaw('EXTRACT(EPOCH FROM (MIN(postulacion_etapas.desde) - MIN(postulaciones.created_at))) AS segundos')
            ->groupBy('postulacion_etapas.postulacion_id')
            ->pluck('segundos')
            ->map(fn ($s) => max(0.0, (float) $s));

        if ($segundos->isEmpty()) {
            return [0, null];
        }

        return [$segundos->count(), round($segundos->avg() / 86400, 1)];
    }
}
