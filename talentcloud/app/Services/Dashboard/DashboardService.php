<?php

namespace App\Services\Dashboard;

use App\DTO\ResumenDelDashboard;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Models\Actividad;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\Vacante;

/**
 * Indicadores del dashboard. Cada consulta pasa por el filtro por empresa: una
 * empresa ve lo suyo y el admin SaaS, toda la plataforma.
 */
class DashboardService
{
    /** El match promedio considera los cálculos de los últimos días (diálogo del prototipo). */
    public const DIAS_DEL_MATCH_PROMEDIO = 30;

    private const VACANTES_RECIENTES = 5;

    private const ACTIVIDAD_RECIENTE = 6;

    public function resumen(): ResumenDelDashboard
    {
        $conMatch = Postulacion::query()
            ->whereNotNull('match_score')
            ->where('match_calculado_at', '>=', now()->subDays(self::DIAS_DEL_MATCH_PROMEDIO));

        $promedio = (clone $conMatch)->avg('match_score');

        return new ResumenDelDashboard(
            vacantesActivas: Vacante::query()->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision])->count(),
            candidatosEvaluados: Evaluacion::query()
                ->join('postulaciones', 'postulaciones.id', '=', 'evaluaciones.postulacion_id')
                ->distinct()
                ->count('postulaciones.candidato_id'),
            matchPromedio: $promedio === null ? null : (int) round((float) $promedio),
            postulacionesConMatch: $conMatch->count(),
            entrevistasHoy: Entrevista::query()
                ->whereBetween('inicia_at', [now()->startOfDay(), now()->endOfDay()])
                ->where('estado', '!=', EstadoEntrevista::Cancelada)
                ->count(),
            vacantesRecientes: Vacante::query()
                ->with('empresa')
                ->withCount('postulaciones')
                ->latest()
                ->limit(self::VACANTES_RECIENTES)
                ->get(),
            actividad: Actividad::query()->latest()->limit(self::ACTIVIDAD_RECIENTE)->get(),
        );
    }
}
