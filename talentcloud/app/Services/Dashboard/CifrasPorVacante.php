<?php

namespace App\Services\Dashboard;

use App\DTO\CifrasPorVacanteData;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Support\CacheDeResumenes;
use Illuminate\Support\Carbon;

/**
 * Cifras por vacante del dashboard (por revisar, esperando, estancadas, mejor
 * match) en una sola lectura agregada de las postulaciones, en vez de una
 * subconsulta por vacante. Una postulación y su vacante son de la misma empresa
 * (clave compuesta), así que basta filtrar por empresa en las postulaciones.
 *
 * Las fechas se comparan como texto ISO 8601, que ordena igual y es más rápido
 * que parsearlas por fila.
 */
class CifrasPorVacante
{
    /** Filas de «Vacantes en curso». */
    public const VACANTES_CON_MOVIMIENTO = 7;

    /** Avisos de cada tipo en «Requiere tu atención». */
    public const AVISOS_POR_TIPO = 3;

    public function calcular(): CifrasPorVacanteData
    {
        return CacheDeResumenes::recordar('por-vacante', fn () => $this->leer());
    }

    private function leer(): CifrasPorVacanteData
    {
        $postulado = EtapaPostulacion::Postulado->value;
        $terminadas = "'".EtapaPostulacion::Contratado->value."', '".EtapaPostulacion::Descartado->value."'";
        $corteRevision = now()->subDays(DashboardService::DIAS_SIN_REVISAR);
        $corteEstancada = now()->subDays(DashboardService::DIAS_ESTANCADA);

        /** @var list<object> $filas */
        $filas = Postulacion::query()
            ->toBase()
            ->join('vacantes', 'vacantes.id', '=', 'postulaciones.vacante_id')
            ->whereIn('vacantes.estado', array_map(fn (EstadoVacante $e) => $e->value, EstadoVacante::activos()))
            ->groupBy('postulaciones.vacante_id', 'vacantes.estado')
            ->selectRaw(
                "postulaciones.vacante_id AS id,
                (vacantes.estado = '".EstadoVacante::Abierta->value."') AS abierta,
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE postulaciones.etapa = '{$postulado}' AND postulaciones.retirada_at IS NULL) AS por_revisar,
                COUNT(*) FILTER (WHERE postulaciones.etapa = '{$postulado}' AND postulaciones.retirada_at IS NULL
                    AND COALESCE(postulaciones.etapa_cambiada_at, postulaciones.created_at) <= ?) AS esperando,
                COUNT(*) FILTER (WHERE postulaciones.etapa NOT IN ({$terminadas}) AND postulaciones.retirada_at IS NULL) AS en_curso,
                COUNT(*) FILTER (WHERE postulaciones.created_at > ? OR postulaciones.etapa_cambiada_at > ?) AS recientes,
                GREATEST(MAX(postulaciones.created_at), MAX(postulaciones.etapa_cambiada_at)) AS ultimo_movimiento",
                [$corteRevision, $corteEstancada, $corteEstancada],
            )
            ->get()
            ->all();

        $movimiento = $this->conMasPorRevisar($filas);

        return new CifrasPorVacanteData(
            movimiento: array_map(fn (object $f) => [
                'id' => (int) $f->id,
                'total' => (int) $f->total,
                'por_revisar' => (int) $f->por_revisar,
            ], $movimiento),
            esperando: $this->esperandoRevision($filas),
            estancadas: $this->estancadas($filas, array_map(fn (object $f) => (int) $f->id, $movimiento), $corteEstancada),
        );
    }

    /**
     * Las que más postulaciones por revisar tienen; a igualdad, las que más postulaciones tienen y, después, las más antiguas.
     *
     * @param  list<object>  $filas
     * @return list<object>
     */
    private function conMasPorRevisar(array $filas): array
    {
        $porRevisar = array_column($filas, 'por_revisar');
        $total = array_column($filas, 'total');
        $id = array_column($filas, 'id');

        array_multisort($porRevisar, SORT_DESC, $total, SORT_DESC, $id, SORT_ASC, $filas);

        return array_slice($filas, 0, self::VACANTES_CON_MOVIMIENTO);
    }

    /**
     * @param  list<object>  $filas
     * @return list<array{id: int, n: int}>
     */
    private function esperandoRevision(array $filas): array
    {
        $esperando = array_values(array_filter($filas, fn (object $f) => $f->esperando > 0));

        $n = array_column($esperando, 'esperando');
        $id = array_column($esperando, 'id');

        array_multisort($n, SORT_DESC, $id, SORT_ASC, $esperando);

        return array_map(
            fn (object $f) => ['id' => (int) $f->id, 'n' => (int) $f->esperando],
            array_slice($esperando, 0, self::AVISOS_POR_TIPO),
        );
    }

    /**
     * Vacantes abiertas con candidatos en curso y sin movimiento hace {@see DashboardService::DIAS_ESTANCADA} días
     * o más (postulación recibida, cambio de etapa o entrevista). Se devuelven las más antiguas y las que
     * aparecen en «Vacantes en curso», para marcarlas allí; de más a menos días.
     *
     * @param  list<object>  $filas
     * @param  list<int>  $idsVisibles
     * @return array<int, int>
     */
    private function estancadas(array $filas, array $idsVisibles, Carbon $corte): array
    {
        $conEntrevistaReciente = array_flip(
            Entrevista::query()
                ->toBase()
                ->join('postulaciones', 'postulaciones.id', '=', 'entrevistas.postulacion_id')
                ->where('entrevistas.inicia_at', '>', $corte)
                ->distinct()
                ->pluck('postulaciones.vacante_id')
                ->all(),
        );

        // Último movimiento por vacante estancada, como texto («2026-09-30 10:15:00»): ordena igual que la fecha.
        $ultimo = [];
        foreach ($filas as $fila) {
            if ($fila->abierta && $fila->en_curso > 0 && $fila->recientes == 0 && ! isset($conEntrevistaReciente[$fila->id])) {
                $ultimo[(int) $fila->id] = (string) $fila->ultimo_movimiento;
            }
        }

        asort($ultimo); // las más antiguas primero

        $conservar = array_flip([...array_slice(array_keys($ultimo), 0, self::AVISOS_POR_TIPO), ...$idsVisibles]);

        $ahora = now();
        $dias = [];
        foreach (array_intersect_key($ultimo, $conservar) as $id => $momento) {
            $dias[$id] = (int) Carbon::parse($momento)->diffInDays($ahora);
        }

        return $dias;
    }
}
