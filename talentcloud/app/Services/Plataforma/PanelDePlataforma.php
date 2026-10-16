<?php

namespace App\Services\Plataforma;

use App\DTO\ResumenDePlataforma;
use App\DTO\UsoDelPlan;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Repositories\Contracts\ReclamacionRepository;
use App\Services\Empresas\CupoDeIa;
use App\Services\Reclamaciones\PlazoDeRespuesta;
use App\Tenancy\EmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Panel del admin SaaS: el estado de las empresas cliente (planes y uso) y un
 * resumen de la calidad de la IA. Solo lo usa el admin; lee sin filtro de
 * empresa, igual que la pantalla "Empresas".
 */
class PanelDePlataforma
{
    /** Días hábiles con los que una hoja pendiente se cuenta "por vencer". */
    public const DIAS_POR_VENCER = 3;

    public function __construct(
        private readonly CalidadDeLaIa $calidad,
        private readonly ReclamacionRepository $reclamaciones,
        private readonly PlazoDeRespuesta $plazo,
    ) {}

    public function resumen(): ResumenDePlataforma
    {
        // Un solo viaje a la base: los conteos del uso del plan van en la misma consulta.
        $empresas = Empresa::query()
            ->withCount([
                'usuarios as usuarios_activos_count' => fn (Builder $q) => $q->whereNull('desactivado_at'),
                'vacantes as vacantes_activas_count' => fn (Builder $q) => $q
                    ->withoutGlobalScope(EmpresaScope::class)
                    ->whereIn('estado', EstadoVacante::activos()),
                'invitaciones as invitaciones_pendientes_count' => fn (Builder $q) => $q
                    ->withoutGlobalScope(EmpresaScope::class)
                    ->whereNull('aceptada_at')
                    ->where('expira_at', '>', now()),
                'postulaciones as postulaciones_con_ia_count' => fn (Builder $q) => $q
                    ->withoutGlobalScope(EmpresaScope::class)
                    ->where('ia_cupo_at', '>=', CupoDeIa::inicioDelMes()),
            ])
            ->orderBy('nombre')
            ->get();

        $activas = $empresas->filter(fn (Empresa $e) => $e->estado === EstadoEmpresa::Activa);

        return new ResumenDePlataforma(
            empresasActivas: $activas->count(),
            empresasSuspendidas: $empresas->count() - $activas->count(),
            porPlan: $activas->countBy(fn (Empresa $e) => $e->plan->value)->all(),
            empresasPorPlan: $activas->groupBy(fn (Empresa $e) => $e->plan->value)
                ->map(fn (Collection $grupo) => $grupo->map(fn (Empresa $e) => ['id' => $e->id, 'nombre' => $e->nombre])->values()->all())
                ->all(),
            cercaDelLimite: $activas
                ->map(fn (Empresa $e) => ['empresa' => $e, 'uso' => new UsoDelPlan(
                    plan: $e->planVigente(),
                    vacantesActivas: (int) $e->getAttribute('vacantes_activas_count'),
                    usuariosActivos: (int) $e->getAttribute('usuarios_activos_count'),
                    invitacionesPendientes: (int) $e->getAttribute('invitaciones_pendientes_count'),
                    postulacionesConIa: (int) $e->getAttribute('postulaciones_con_ia_count'),
                    pruebaHasta: $e->enPrueba() ? $e->prueba_hasta : null,
                )])
                ->filter(fn (array $fila) => $fila['uso']->cercaDelLimite())
                ->values(),
            ia: $this->calidad->indicadores(),
            enPrueba: $activas->filter(fn (Empresa $e) => $e->enPrueba())->count(),
            solicitudesDePlan: $activas
                ->filter(fn (Empresa $e) => $e->plan_solicitado !== null)
                ->sortBy(fn (Empresa $e) => $e->plan_solicitado_at)
                ->values(),
            reclamaciones: $this->reclamaciones->resumen($this->plazo->sumarDiasHabiles(now(), self::DIAS_POR_VENCER)),
        );
    }
}
