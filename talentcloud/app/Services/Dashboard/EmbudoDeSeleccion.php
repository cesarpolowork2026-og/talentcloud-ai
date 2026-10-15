<?php

namespace App\Services\Dashboard;

use App\DTO\EmbudoDeSeleccionData;
use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use App\Support\CacheDeResumenes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * @param  int  $dias  Periodo: los últimos `$dias` días completos, hoy incluido (el mismo que la evolución de postulaciones).
     */
    public function calcular(?int $vacanteId = null, int $dias = self::DIAS): EmbudoDeSeleccionData
    {
        return CacheDeResumenes::recordar("embudo-{$dias}-".($vacanteId ?? 'todas'), fn () => $this->leer($vacanteId, $dias));
    }

    private function leer(?int $vacanteId, int $dias): EmbudoDeSeleccionData
    {
        $desde = Carbon::today()->subDays($dias - 1);

        $postulaciones = Postulacion::query()
            ->where('postulaciones.created_at', '>=', $desde)
            ->when($vacanteId !== null, fn (Builder $q) => $q->where('postulaciones.vacante_id', $vacanteId));

        $conteos = $this->conteos(clone $postulaciones);
        $total = $conteos['total'];
        $etapas = self::etapasDelEmbudo();
        $porRango = $this->postulacionesPorRangoAlcanzado(clone $postulaciones);

        $filas = [];
        $anterior = null;
        foreach ($etapas as $rango => $etapa) {
            $alcanzaron = (int) $porRango->filter(fn (int $n, int $r) => $r >= $rango)->sum();
            $filas[] = [
                'etapa' => $etapa,
                'total' => $alcanzaron,
                'porcentaje' => $total > 0 ? (int) round($alcanzaron * 100 / $total) : 0,
                // Qué parte de quienes llegaron a la etapa anterior llegó a esta (null en la primera o sin base).
                'conversion' => $anterior === null || $anterior === 0 ? null : (int) round($alcanzaron * 100 / $anterior),
            ];
            $anterior = $alcanzaron;
        }

        [$contrataciones, $diasHastaContratar] = $this->tiempoHastaContratar($desde, $vacanteId);
        $tiempoPorEtapa = $this->tiempoPorEtapa($desde, $vacanteId);

        return new EmbudoDeSeleccionData(
            dias: $dias,
            desde: $desde,
            hasta: Carbon::today(),
            total: $total,
            etapas: $filas,
            descartadas: $conteos['descartadas'],
            retiradas: $conteos['retiradas'],
            diasHastaContratar: $diasHastaContratar,
            contrataciones: $contrataciones,
            porOrigen: $conteos['porOrigen'],
            tiempoPorEtapa: $tiempoPorEtapa,
        );
    }

    /**
     * Total, descartadas, retiradas y postulaciones por origen en una sola
     * lectura (con consultas separadas, en la vista de toda la plataforma se
     * recorrían las postulaciones del periodo cuatro veces).
     *
     * @param  Builder<Postulacion>  $postulaciones
     * @return array{total: int, descartadas: int, retiradas: int, porOrigen: array<string, int>}
     */
    private function conteos(Builder $postulaciones): array
    {
        $consulta = $postulaciones->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE etapa = ? AND retirada_at IS NULL) AS descartadas', [EtapaPostulacion::Descartado->value])
            ->selectRaw('COUNT(*) FILTER (WHERE retirada_at IS NOT NULL) AS retiradas');

        foreach (OrigenPostulacion::cases() as $i => $origen) {
            $consulta->selectRaw("COUNT(*) FILTER (WHERE origen = ?) AS origen_{$i}", [$origen->value]);
        }

        $fila = (array) $consulta->first();
        $porOrigen = [];
        foreach (OrigenPostulacion::cases() as $i => $origen) {
            $porOrigen[$origen->value] = (int) $fila["origen_{$i}"];
        }

        return [
            'total' => (int) $fila['total'],
            'descartadas' => (int) $fila['descartadas'],
            'retiradas' => (int) $fila['retiradas'],
            'porOrigen' => $porOrigen,
        ];
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
     * Días medios que las postulaciones del periodo pasaron en cada etapa antes de salir de ella (avanzar, volver
     * atrás o ser descartadas). Solo cuentan las estancias ya terminadas: quien sigue en una etapa aún no tiene
     * duración. Contratado no se mide (no tiene salida). Las etapas sin ninguna estancia terminada no aparecen.
     *
     * @return list<array{etapa: EtapaPostulacion, dias: float, muestras: int}>
     */
    private function tiempoPorEtapa(Carbon $desde, ?int $vacanteId): array
    {
        $historial = PostulacionEtapa::query()
            ->join('postulaciones', 'postulaciones.id', '=', 'postulacion_etapas.postulacion_id')
            ->where('postulaciones.created_at', '>=', $desde)
            ->when($vacanteId !== null, fn (Builder $q) => $q->where('postulaciones.vacante_id', $vacanteId))
            ->toBase()
            ->selectRaw('postulacion_etapas.etapa AS etapa, postulacion_etapas.desde AS desde, LEAD(postulacion_etapas.desde) OVER (PARTITION BY postulacion_etapas.postulacion_id ORDER BY postulacion_etapas.desde, postulacion_etapas.id) AS siguiente');

        $filas = DB::query()->fromSub($historial, 'historial')
            ->whereNotNull('siguiente')
            ->selectRaw('etapa, AVG(EXTRACT(EPOCH FROM (siguiente - desde))) AS segundos, COUNT(*) AS muestras')
            ->groupBy('etapa')
            ->get()
            ->keyBy('etapa');

        $medidas = [];
        foreach (array_slice(self::etapasDelEmbudo(), 0, -1) as $etapa) {
            $fila = $filas->get($etapa->value);

            if ($fila !== null) {
                $medidas[] = ['etapa' => $etapa, 'dias' => round((float) $fila->segundos / 86400, 1), 'muestras' => (int) $fila->muestras];
            }
        }

        return $medidas;
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
