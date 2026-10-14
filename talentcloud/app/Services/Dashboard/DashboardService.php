<?php

namespace App\Services\Dashboard;

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
use Illuminate\Support\Carbon;

/**
 * Indicadores del dashboard. Cada consulta pasa por el filtro por empresa: una
 * empresa ve lo suyo y el admin SaaS, toda la plataforma.
 *
 * Responde, en orden: qué pide una acción hoy, cómo van las vacantes y cuánto
 * trabajo de lectura de CV ahorró la IA (con el supuesto a la vista).
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

    private const VACANTES_CON_MOVIMIENTO = 5;

    public function __construct(
        private readonly ResumenDeAgenda $agenda,
        private readonly TenantContext $tenant,
    ) {}

    private const AVISOS_POR_TIPO = 3;

    public function resumen(): ResumenDelDashboard
    {
        $postulaciones = $this->cifrasDePostulaciones();
        $entrevistas = $this->agenda->calcular();
        $estancadas = $this->estancadas();

        return new ResumenDelDashboard(
            vacantesActivas: Vacante::query()->whereIn('estado', EstadoVacante::activos())->count(),
            postulantes: (int) $postulaciones->postulantes,
            postulacionesNuevas: (int) $postulaciones->nuevas,
            porRevisar: (int) $postulaciones->por_revisar,
            entrevistasHoy: $entrevistas->hoy,
            entrevistasProximas: $entrevistas->proximas,
            analizadasConIa: (int) $postulaciones->con_ia,
            minutosPorCv: (int) config('talentcloud.ahorro.minutos_por_cv'),
            atencion: $this->atencion($entrevistas->hoy, $entrevistas->sin_confirmar, $estancadas),
            estancadas: array_map(fn (array $v) => $v['dias'], $estancadas),
            vacantesConMovimiento: Vacante::query()
                ->with('empresa')
                ->whereIn('estado', EstadoVacante::activos())
                ->withCount([
                    'postulaciones',
                    'postulaciones as por_revisar' => fn (Builder $q) => $this->sinRevisar($q),
                ])
                ->withMax('postulaciones as mejor_match', 'match_score')
                ->orderByDesc('por_revisar')
                ->orderByDesc('postulaciones_count')
                ->orderBy('id')
                ->limit(self::VACANTES_CON_MOVIMIENTO)
                ->get(),
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
     * Vacantes abiertas con candidatos en curso y sin movimiento hace {@see self::DIAS_ESTANCADA} días o más,
     * las más antiguas primero, indexadas por id. Movimiento = postulación recibida, cambio de etapa o entrevista.
     *
     * @return array<int, array{titulo: string, dias: int}>
     */
    private function estancadas(): array
    {
        $corte = now()->subDays(self::DIAS_ESTANCADA);
        $enCurso = [EtapaPostulacion::Contratado, EtapaPostulacion::Descartado];

        $vacantes = Vacante::query()
            ->where('estado', EstadoVacante::Abierta)
            ->whereHas('postulaciones', fn (Builder $q) => $q->whereNotIn('etapa', $enCurso)->whereNull('retirada_at'))
            ->whereDoesntHave('postulaciones', fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->where('created_at', '>', $corte)->orWhere('etapa_cambiada_at', '>', $corte),
            ))
            ->whereDoesntHave('postulaciones.entrevistas', fn (Builder $q) => $q->where('inicia_at', '>', $corte))
            ->withMax('postulaciones as ultima_recibida', 'created_at')
            ->withMax('postulaciones as ultimo_cambio', 'etapa_cambiada_at')
            ->tap($this->conEmpresaSiEsGlobal(...))
            ->get(['id', 'titulo', 'empresa_id']);

        $estancadas = [];
        foreach ($vacantes as $vacante) {
            $ultimo = collect([$vacante->getAttribute('ultima_recibida'), $vacante->getAttribute('ultimo_cambio')])
                ->filter()
                ->map(fn ($f) => Carbon::parse($f))
                ->max();

            $estancadas[$vacante->id] = ['titulo' => Formato::vacante($vacante), 'dias' => (int) $ultimo->diffInDays(now())];
        }

        uasort($estancadas, fn (array $a, array $b) => $b['dias'] <=> $a['dias']);

        return $estancadas;
    }

    /**
     * Lo que pide una acción: primero lo de hoy y lo que otra persona espera
     * (entrevistas, postulaciones que llevan días sin mirarse) y después lo que se puede mejorar.
     *
     * @param  array<int, array{titulo: string, dias: int}>  $estancadas
     * @return list<array{icono: string, texto: string, ruta: string, parametros: array<string, mixed>, urgente: bool}>
     */
    private function atencion(int $entrevistasHoy, int $entrevistasSinConfirmar, array $estancadas): array
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

        $esperando = Vacante::query()
            ->whereIn('estado', EstadoVacante::activos())
            ->whereHas('postulaciones', fn (Builder $q) => $this->sinRevisar($q, self::DIAS_SIN_REVISAR))
            ->withCount(['postulaciones as esperando' => fn (Builder $q) => $this->sinRevisar($q, self::DIAS_SIN_REVISAR)])
            ->orderByDesc('esperando')
            ->limit(self::AVISOS_POR_TIPO)
            ->tap($this->conEmpresaSiEsGlobal(...))
            ->get(['id', 'titulo', 'empresa_id']);

        foreach ($esperando as $vacante) {
            $n = (int) $vacante->getAttribute('esperando');
            $avisos[] = [
                'icono' => 'hourglass',
                'texto' => Formato::vacante($vacante).': '.($n === 1 ? '1 postulación lleva' : "{$n} postulaciones llevan").' más de '.self::DIAS_SIN_REVISAR.' días sin revisar.',
                'ruta' => 'seleccion.show',
                'parametros' => ['vacante' => $vacante->id],
                'urgente' => false,
            ];
        }

        $sinPostulaciones = Vacante::query()
            ->where('estado', EstadoVacante::Abierta)
            ->where('publicada_at', '<=', now()->subDays(self::DIAS_SIN_POSTULACIONES))
            ->whereDoesntHave('postulaciones')
            ->orderBy('publicada_at')
            ->limit(self::AVISOS_POR_TIPO)
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

        foreach (array_slice($estancadas, 0, self::AVISOS_POR_TIPO, true) as $id => $vacante) {
            $avisos[] = [
                'icono' => 'pause-circle',
                'texto' => "{$vacante['titulo']} lleva {$vacante['dias']} días sin avances: hay candidatos en proceso pero nadie se movió. Revisa el tablero.",
                'ruta' => 'seleccion.show',
                'parametros' => ['vacante' => $id],
                'urgente' => false,
            ];
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

    /**
     * Postulaciones que esperan una primera mirada: en «Postulado» y sin retirar;
     * con $dias, solo las que llevan al menos ese tiempo así.
     *
     * @param  Builder<Postulacion>  $consulta
     */
    private function sinRevisar(Builder $consulta, ?int $dias = null): void
    {
        $consulta->where('etapa', EtapaPostulacion::Postulado)->whereNull('retirada_at');

        if ($dias !== null) {
            // Una postulación que nunca cambió de etapa lleva el tiempo que tiene desde que llegó.
            $consulta->whereRaw('COALESCE(etapa_cambiada_at, created_at) <= ?', [now()->subDays($dias)]);
        }
    }
}
