<?php

namespace App\Services\Dashboard;

use App\DTO\CifrasPorVacanteData;
use App\DTO\ResumenDelDashboard;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\MotorMatching;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Services\Entrevistas\ResumenDeAgenda;
use App\Support\CacheDeResumenes;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Indicadores del dashboard. Cada consulta pasa por el filtro por empresa: una
 * empresa ve lo suyo y el admin SaaS toda la plataforma. Responde qué pide una
 * acción hoy, cómo van las vacantes y cuánto trabajo ahorró la IA. Las cifras
 * por vacante salen de {@see CifrasPorVacante}.
 */
class DashboardService
{
    /** La IA se cuenta sobre los análisis de los últimos días. */
    public const DIAS_DEL_AHORRO = 30;

    /** «Nuevas» son las postulaciones de los últimos días. */
    public const DIAS_NUEVAS = 7;

    /** Una postulación sin revisar más de estos días se destaca. */
    public const DIAS_SIN_REVISAR = 3;

    /** Una vacante abierta sin ninguna postulación tras estos días merece una mirada. */
    public const DIAS_SIN_POSTULACIONES = 7;

    /**
     * Una vacante abierta con candidatos en curso y sin ningún movimiento (postulación nueva, cambio de etapa
     * o entrevista) en estos días se considera estancada.
     */
    public const DIAS_ESTANCADA = 14;

    public function __construct(
        private readonly ResumenDeAgenda $agenda,
        private readonly CifrasPorVacante $porVacante,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Todo el resumen se recuerda 30 s por empresa: cambiar el periodo, el filtro del embudo o abrir una
     * vista previa vuelve a pintar la pantalla, y no hace falta repetir por eso la veintena de consultas.
     */
    public function resumen(): ResumenDelDashboard
    {
        return CacheDeResumenes::recordar('resumen', fn () => $this->calcularResumen(), 30);
    }

    private function calcularResumen(): ResumenDelDashboard
    {
        $postulaciones = $this->cifrasDePostulaciones();
        $entrevistas = $this->agenda->calcular();
        $cifras = $this->porVacante->calcular();
        $vacantes = $this->vacantesMostradas($cifras);

        return new ResumenDelDashboard(
            vacantesActivas: Vacante::query()->whereIn('estado', EstadoVacante::activos())->count(),
            postulantes: (int) $postulaciones->postulantes,
            postulacionesNuevas: (int) $postulaciones->nuevas,
            porRevisar: (int) $postulaciones->por_revisar,
            entrevistasHoy: $entrevistas->hoy,
            entrevistasProximas: $entrevistas->proximas,
            analizadasConIa: (int) $postulaciones->con_ia,
            minutosPorCv: (int) config('talentcloud.ahorro.minutos_por_cv'),
            atencion: $this->atencion($entrevistas->hoy, $entrevistas->sin_confirmar, $cifras, $vacantes),
            estancadas: $cifras->estancadas,
            vacantesConMovimiento: $this->vacantesConMovimiento($cifras, $vacantes),
        );
    }

    /** Todas las cifras de postulaciones en una sola lectura. */
    private function cifrasDePostulaciones(): object
    {
        return CacheDeResumenes::recordar('postulaciones', fn () => $this->leerCifrasDePostulaciones());
    }

    private function leerCifrasDePostulaciones(): object
    {
        $activas = implode(',', array_map(fn (EstadoVacante $e) => "'{$e->value}'", EstadoVacante::activos()));

        return Postulacion::query()
            ->toBase()
            ->selectRaw(
                "COUNT(DISTINCT candidato_id) AS postulantes,
                COUNT(*) FILTER (WHERE created_at >= ?) AS nuevas,
                COUNT(*) FILTER (WHERE etapa = ? AND retirada_at IS NULL AND vacante_id IN (SELECT id FROM vacantes WHERE estado IN ({$activas}))) AS por_revisar,
                COUNT(*) FILTER (WHERE match_motor = ? AND match_calculado_at >= ?) AS con_ia",
                [
                    now()->subDays(self::DIAS_NUEVAS),
                    EtapaPostulacion::Postulado->value,
                    MotorMatching::Http->value,
                    now()->subDays(self::DIAS_DEL_AHORRO),
                ],
            )
            ->first() ?? (object) ['postulantes' => 0, 'nuevas' => 0, 'por_revisar' => 0, 'con_ia' => 0];
    }

    /**
     * Las vacantes que el dashboard nombra (las de «Vacantes en curso» y las de los avisos), en una sola lectura.
     *
     * @return Collection<int, Vacante>
     */
    private function vacantesMostradas(CifrasPorVacanteData $cifras): Collection
    {
        $ids = array_unique([
            ...array_column($cifras->movimiento, 'id'),
            ...array_column($cifras->esperando, 'id'),
            ...array_keys($cifras->estancadas),
        ]);

        if ($ids === []) {
            return new Collection;
        }

        return Vacante::query()
            ->whereIn('id', $ids)
            ->tap($this->conEmpresaSiEsGlobal(...))
            ->get()
            ->keyBy('id');
    }

    /**
     * «Vacantes en curso»: las que más postulaciones por revisar tienen. Si hay menos de las que caben, se
     * completa con vacantes activas aún sin postulaciones (lo último en el orden de siempre).
     *
     * @param  Collection<int, Vacante>  $vacantes
     * @return Collection<int, Vacante>
     */
    private function vacantesConMovimiento(CifrasPorVacanteData $cifras, Collection $vacantes): Collection
    {
        $lista = collect($cifras->movimiento)
            ->map(function (array $fila) use ($vacantes): ?Vacante {
                $vacante = $vacantes->get($fila['id']);
                $vacante?->setAttribute('postulaciones_count', $fila['total']);
                $vacante?->setAttribute('por_revisar', $fila['por_revisar']);
                $vacante?->setAttribute('mejor_match', $fila['mejor_match']);

                return $vacante;
            })
            ->filter()
            ->values();

        $faltan = CifrasPorVacante::VACANTES_CON_MOVIMIENTO - $lista->count();

        if ($faltan > 0 && count($cifras->movimiento) < CifrasPorVacante::VACANTES_CON_MOVIMIENTO) {
            $sinPostulaciones = Vacante::query()
                ->whereIn('estado', EstadoVacante::activos())
                ->whereDoesntHave('postulaciones')
                ->orderBy('id')
                ->limit($faltan)
                ->tap($this->conEmpresaSiEsGlobal(...))
                ->get()
                ->each(fn (Vacante $v) => $v->setAttribute('postulaciones_count', 0)->setAttribute('por_revisar', 0)->setAttribute('mejor_match', null));

            $lista = $lista->concat($sinPostulaciones)->values();
        }

        return $lista;
    }

    /**
     * Lo que pide una acción: primero lo de hoy y lo que otra persona espera
     * (entrevistas, postulaciones que llevan días sin mirarse) y después lo que se puede mejorar.
     *
     * @param  Collection<int, Vacante>  $vacantes
     * @return list<array{icono: string, texto: string, ruta: string, parametros: array<string, mixed>, urgente: bool}>
     */
    private function atencion(int $entrevistasHoy, int $entrevistasSinConfirmar, CifrasPorVacanteData $cifras, Collection $vacantes): array
    {
        $avisos = [];

        if ($entrevistasHoy > 0) {
            $avisos[] = [
                'icono' => 'calendar-check',
                'texto' => $entrevistasHoy === 1 ? 'Tienes 1 entrevista hoy.' : "Tienes {$entrevistasHoy} entrevistas hoy.",
                'ruta' => 'entrevistas.index',
                'parametros' => [],
                'urgente' => true,
            ];
        }

        if ($entrevistasSinConfirmar > 0) {
            $avisos[] = [
                'icono' => 'clock',
                'texto' => $entrevistasSinConfirmar === 1 ? '1 entrevista por confirmar.' : "{$entrevistasSinConfirmar} entrevistas por confirmar.",
                'ruta' => 'entrevistas.index',
                'parametros' => [],
                'urgente' => false,
            ];
        }

        foreach ($cifras->esperando as $fila) {
            if ($vacante = $vacantes->get($fila['id'])) {
                $avisos[] = [
                    'icono' => 'hourglass',
                    'texto' => Formato::vacante($vacante).': '.($fila['n'] === 1 ? '1 postulación lleva' : "{$fila['n']} postulaciones llevan").' más de '.self::DIAS_SIN_REVISAR.' días sin revisar.',
                    'ruta' => 'seleccion.show',
                    'parametros' => ['vacante' => $vacante->id],
                    'urgente' => false,
                ];
            }
        }

        $sinPostulaciones = Vacante::query()
            ->where('estado', EstadoVacante::Abierta)
            ->where('publicada_at', '<=', now()->subDays(self::DIAS_SIN_POSTULACIONES))
            ->whereDoesntHave('postulaciones')
            ->orderBy('publicada_at')
            ->limit(CifrasPorVacante::AVISOS_POR_TIPO)
            ->tap($this->conEmpresaSiEsGlobal(...))
            ->get(['id', 'titulo', 'empresa_id']);

        foreach ($sinPostulaciones as $vacante) {
            $avisos[] = [
                'icono' => 'lightning',
                'texto' => Formato::vacante($vacante).' lleva más de '.self::DIAS_SIN_POSTULACIONES.' días abierta sin postulaciones: revisa el título y los requisitos.',
                'ruta' => 'vacantes.show',
                'parametros' => ['vacante' => $vacante->id],
                'urgente' => false,
            ];
        }

        foreach (array_slice($cifras->estancadas, 0, CifrasPorVacante::AVISOS_POR_TIPO, true) as $id => $dias) {
            if ($vacante = $vacantes->get($id)) {
                $avisos[] = [
                    'icono' => 'pause-circle',
                    'texto' => Formato::vacante($vacante)." lleva {$dias} días sin avances: hay candidatos en proceso pero nadie se movió. Revisa el tablero.",
                    'ruta' => 'seleccion.show',
                    'parametros' => ['vacante' => $id],
                    'urgente' => false,
                ];
            }
        }

        return $avisos;
    }

    /**
     * El nombre de la empresa solo se muestra (y se lee) en la vista global del admin SaaS: para el
     * personal de una empresa sería una consulta de más por cada lista.
     *
     * @param  Builder<Vacante>  $consulta
     */
    private function conEmpresaSiEsGlobal(Builder $consulta): void
    {
        if ($this->tenant->esGlobal()) {
            $consulta->with('empresa:id,nombre');
        }
    }
}
