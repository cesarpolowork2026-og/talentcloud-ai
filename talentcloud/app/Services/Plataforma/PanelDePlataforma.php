<?php

namespace App\Services\Plataforma;

use App\DTO\ResumenDePlataforma;
use App\DTO\UsoDelPlan;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoProcesamientoCv;
use App\Enums\EstadoVacante;
use App\Enums\Rol;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Support\Formato;
use App\Tenancy\EmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel del admin SaaS: el negocio (empresas cliente, planes, ingreso mensual estimado), el crecimiento semanal, el
 * estado del sistema y sus últimas acciones. Solo lo usa el admin; lee sin filtro de empresa, igual que la pantalla
 * «Empresas». No mira dentro de los procesos de selección: eso es de cada empresa (y el admin solo los supervisa).
 */
class PanelDePlataforma
{
    /** Semanas que muestra el gráfico de crecimiento. */
    public const SEMANAS = 8;

    public function __construct(private readonly RegistroDeAuditoria $auditoria) {}

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
            ])
            ->orderBy('nombre')
            ->get();

        $activas = $empresas->filter(fn (Empresa $e) => $e->estado === EstadoEmpresa::Activa);

        return new ResumenDePlataforma(
            empresasActivas: $activas->count(),
            empresasSuspendidas: $empresas->count() - $activas->count(),
            porPlan: $activas->countBy(fn (Empresa $e) => $e->planContratado()->value)->all(),
            empresasPorPlan: $activas->groupBy(fn (Empresa $e) => $e->planContratado()->value)
                ->map(fn (Collection $grupo) => $grupo->map(fn (Empresa $e) => ['id' => $e->id, 'nombre' => $e->nombre])->values()->all())
                ->all(),
            cercaDelLimite: $activas
                ->map(fn (Empresa $e) => ['empresa' => $e, 'uso' => new UsoDelPlan(
                    plan: $e->planVigente(),
                    vacantesActivas: (int) $e->getAttribute('vacantes_activas_count'),
                    usuariosActivos: (int) $e->getAttribute('usuarios_activos_count'),
                    invitacionesPendientes: (int) $e->getAttribute('invitaciones_pendientes_count'),
                    pruebaHasta: $e->enPrueba() ? $e->prueba_hasta : null,
                )])
                ->filter(fn (array $fila) => $fila['uso']->cercaDelLimite())
                ->values(),
            enPrueba: $activas->filter(fn (Empresa $e) => $e->enPrueba())->count(),
            planesPorVencer: $activas
                ->filter(fn (Empresa $e) => $e->planVencido() || $e->planVencePronto())
                ->sortBy(fn (Empresa $e) => $e->plan_vence_at)
                ->values(),
            solicitudesDePlan: $activas
                ->filter(fn (Empresa $e) => $e->plan_solicitado !== null)
                ->sortBy(fn (Empresa $e) => $e->plan_solicitado_at)
                ->values(),
            usuariosDeEmpresas: User::query()->whereNotNull('empresa_id')->whereNull('desactivado_at')->count(),
            postulantes: User::role(Rol::Postulante->value)->count(),
            vacantesActivas: Vacante::query()->whereIn('estado', EstadoVacante::activos())->count(),
            postulacionesDelMes: Postulacion::query()->where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            // Referencia con precios de lista: Enterprise se cotiza y no suma; las pruebas aún no pagan ni los planes vencidos.
            ingresoMensual: $activas->reject(fn (Empresa $e) => $e->planContratado()->seCotiza())->sum(fn (Empresa $e) => $e->planContratado()->precioMensual()),
            enterpriseSinPrecio: $activas->filter(fn (Empresa $e) => $e->planContratado()->seCotiza())->count(),
            crecimiento: $this->crecimiento(),
            trabajosFallidos: DB::table('failed_jobs')->count(),
            trabajosEnCola: DB::table('jobs')->count(),
            cvConError: Cv::query()->where('estado_procesamiento', EstadoProcesamientoCv::Error)->count(),
            ultimasAcciones: $this->auditoria->ultimas(6),
        );
    }

    /**
     * Empresas nuevas y postulaciones recibidas por semana (lunes a domingo), de la más antigua a la actual.
     *
     * @return list<array{semana: string, empresas: int, postulaciones: int}>
     */
    private function crecimiento(): array
    {
        $inicioActual = Carbon::today()->startOfWeek();
        $desde = $inicioActual->copy()->subWeeks(self::SEMANAS - 1);

        $porSemana = fn (Builder $consulta): array => $consulta
            ->where('created_at', '>=', $desde)
            ->toBase()
            ->selectRaw("date_trunc('week', created_at)::date AS semana, COUNT(*) AS n")
            ->groupBy('semana')
            ->pluck('n', 'semana')
            ->map(fn ($n) => (int) $n)
            ->all();

        $empresas = $porSemana(Empresa::query());
        $postulaciones = $porSemana(Postulacion::query());

        $filas = [];
        for ($i = 0; $i < self::SEMANAS; $i++) {
            $lunes = $desde->copy()->addWeeks($i);
            $clave = $lunes->toDateString();

            $filas[] = [
                'semana' => Formato::fechaCorta($lunes),
                'empresas' => $empresas[$clave] ?? 0,
                'postulaciones' => $postulaciones[$clave] ?? 0,
            ];
        }

        return $filas;
    }
}
