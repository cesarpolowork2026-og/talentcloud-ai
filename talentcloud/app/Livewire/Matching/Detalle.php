<?php

namespace App\Livewire\Matching;

use App\Enums\EstadoProcesamientoCv;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Matching\ExplicacionDelPuntaje;
use App\Matching\MatchResult;
use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MarcarResultadoIncorrecto;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Matching inteligente" (prototipo: isMatching): coincidencia por requisito
 * con su evidencia. Es una sugerencia explicada; la decisión es del reclutador.
 */
class Detalle extends Component
{
    /** Segundos que se espera el resultado de «Recalcular» antes de avisar que sigue en curso. */
    public const ESPERA_MAXIMA = 90;

    #[Locked]
    public int $postulacionId;

    /** Momento (ISO 8601) en que se pidió recalcular; null si no hay un cálculo esperándose. */
    #[Locked]
    public ?string $recalculoDesde = null;

    /** Porcentaje que tenía la postulación al pedir el recálculo, para decir cuánto cambió. */
    #[Locked]
    public ?int $porcentajeAnterior = null;

    /** @var array{tono: string, texto: string}|null */
    public ?array $aviso = null;

    public function mount(Postulacion $postulacion): void
    {
        $this->authorize('viewAny', Vacante::class);
        $this->authorize('view', $postulacion);

        $this->postulacionId = $postulacion->id;
    }

    /** $marcar: lo que dice el botón pulsado (marcar o quitar la marca), no un "alternar". */
    public function marcarIncorrecto(int $requisitoId, bool $marcar, MarcarResultadoIncorrecto $marcas): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        $requisito = Requisito::where('vacante_id', $postulacion->vacante_id)->find($requisitoId);
        abort_if($requisito === null, 404);

        /** @var User $user */
        $user = auth()->user();

        $marcar ? $marcas->marcar($postulacion, $requisito->id, $user) : $marcas->quitar($postulacion, $requisito->id, $user);
    }

    public function recalcular(): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        $this->porcentajeAnterior = $postulacion->matchPorcentaje();
        $this->recalculoDesde = now()->toIso8601String();
        $this->aviso = null;

        CalcularMatch::para($postulacion);
    }

    /**
     * Mientras se espera un recálculo: si ya hay un resultado posterior al pedido se avisa
     * cuánto cambió; si pasa demasiado tiempo se avisa que sigue en curso y se deja de esperar.
     */
    private function revisarRecalculo(Postulacion $postulacion): void
    {
        if ($this->recalculoDesde === null) {
            return;
        }

        $pedido = Carbon::parse($this->recalculoDesde);
        $calculado = $postulacion->match_calculado_at;

        if ($calculado !== null && $calculado->greaterThanOrEqualTo($pedido)) {
            $nuevo = $postulacion->matchPorcentaje();
            $antes = $this->porcentajeAnterior;

            $this->aviso = match (true) {
                $nuevo === null => ['tono' => 'aviso', 'texto' => 'No se pudo calcular la coincidencia: falta un CV con texto o la vacante no tiene requisitos.'],
                $antes === null => ['tono' => 'ok', 'texto' => "Coincidencia calculada: {$nuevo} %."],
                $antes === $nuevo => ['tono' => 'ok', 'texto' => "Coincidencia actualizada: sigue en {$nuevo} %."],
                default => ['tono' => 'ok', 'texto' => "Coincidencia actualizada: de {$antes} % a {$nuevo} %."],
            };
            $this->recalculoDesde = null;

            return;
        }

        if ($pedido->diffInSeconds(now(), true) > self::ESPERA_MAXIMA) {
            $this->aviso = ['tono' => 'aviso', 'texto' => 'El cálculo sigue en curso: el servicio de análisis está ocupado. Vuelve a abrir esta pantalla en unos minutos; el resultado se guarda solo.'];
            $this->recalculoDesde = null;
        }
    }

    public function render(): View
    {
        $postulacion = $this->postulacion()->load(['candidato.cvActual', 'vacante.empresa', 'vacante.requisitos.competencia']);
        $this->revisarRecalculo($postulacion);
        $resultado = $postulacion->match_detalle ? MatchResult::desdeArreglo($postulacion->match_detalle) : null;

        /** @var User $user */
        $user = auth()->user();

        $explicacion = $resultado && $postulacion->vacante ? ExplicacionDelPuntaje::de($resultado, $postulacion->vacante->requisitos) : null;
        $tipos = $postulacion->vacante?->requisitos->mapWithKeys(fn (Requisito $r) => [$r->id => $r->tipo]) ?? collect();
        $cv = $postulacion->candidato?->cvActual;

        // Las postulaciones de la vacante con matching, de mayor a menor: también dan la posición de esta.
        $otras = Postulacion::query()
            ->where('vacante_id', $postulacion->vacante_id)
            ->whereNotNull('match_score')
            ->with('candidato')
            ->orderByDesc('match_score')
            ->get();
        $posicion = $otras->search(fn (Postulacion $p) => $p->id === $postulacion->id);

        return view('livewire.matching.detalle', [
            'postulacion' => $postulacion,
            'resultado' => $resultado,
            'explicacion' => $explicacion,
            'posicion' => $posicion === false ? null : $posicion + 1,
            'totalDeLaVacante' => $otras->count(),
            'obligatorios' => $tipos->filter(fn (TipoRequisito $t) => $t === TipoRequisito::Obligatorio)->keys()->all(),
            'marcados' => MatchFeedback::where('postulacion_id', $postulacion->id)->where('user_id', $user->id)->pluck('requisito_id')->all(),
            'procesando' => $resultado === null && in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true),
            'recalculando' => $this->recalculoDesde !== null,
            'puedeEditar' => $user->can('update', $postulacion),
            'otras' => $otras
                ->mapWithKeys(fn (Postulacion $p) => [$p->id => $p->candidato?->nombre.' · '.$p->matchPorcentaje().'%'])
                ->all(),
            // Vista global (admin SaaS): los procesos del mismo candidato en otras vacantes, de cualquier empresa.
            'otrosProcesos' => app(TenantContext::class)->esGlobal()
                ? Postulacion::query()
                    ->where('candidato_id', $postulacion->candidato_id)
                    ->whereKeyNot($postulacion->id)
                    ->with('vacante.empresa:id,nombre')
                    ->latest()
                    ->get()
                : collect(),
        ])->title('Matching · '.$postulacion->candidato?->nombre);
    }

    /** Con el filtro por empresa, una postulación de otra empresa no se encuentra. */
    private function postulacion(): Postulacion
    {
        $postulacion = Postulacion::find($this->postulacionId);

        abort_if($postulacion === null, 404);

        return $postulacion;
    }
}
