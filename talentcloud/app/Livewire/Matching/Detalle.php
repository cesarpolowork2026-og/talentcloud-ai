<?php

namespace App\Livewire\Matching;

use App\Enums\EstadoProcesamientoCv;
use App\Enums\EstadoVacante;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Matching\MatchResult;
use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MarcarResultadoIncorrecto;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Matching inteligente" (prototipo: isMatching): coincidencia por requisito
 * con su evidencia. Es una sugerencia explicada; la decisión es del reclutador.
 */
class Detalle extends Component
{
    #[Locked]
    public ?int $postulacionId = null;

    public function mount(?Postulacion $postulacion = null): void
    {
        $this->authorize('viewAny', Vacante::class);

        // /matching sin postulación: la de mayor coincidencia de la vacante activa más reciente.
        $postulacion ??= Postulacion::query()
            ->whereNotNull('match_score')
            ->whereHas('vacante', fn ($q) => $q->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision]))
            ->orderByDesc('match_calculado_at')
            ->orderByDesc('match_score')
            ->first();

        if ($postulacion !== null) {
            $this->authorize('view', $postulacion);
            $this->postulacionId = $postulacion->id;
        }
    }

    public function marcarIncorrecto(int $requisitoId, MarcarResultadoIncorrecto $marcar): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        $requisito = Requisito::where('vacante_id', $postulacion->vacante_id)->find($requisitoId);
        abort_if($requisito === null, 404);

        /** @var User $user */
        $user = auth()->user();

        $marcar->alternar($postulacion, $requisito->id, $user);
    }

    public function recalcular(): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        CalcularMatch::para($postulacion);
    }

    public function render(): View
    {
        if ($this->postulacionId === null) {
            return view('livewire.matching.detalle', ['postulacion' => null])->title('Matching inteligente');
        }

        $postulacion = $this->postulacion()->load(['candidato.cvActual', 'vacante.empresa', 'vacante.requisitos']);
        $resultado = $postulacion->match_detalle ? MatchResult::desdeArreglo($postulacion->match_detalle) : null;

        /** @var User $user */
        $user = auth()->user();

        $tipos = $postulacion->vacante?->requisitos->mapWithKeys(fn (Requisito $r) => [$r->id => $r->tipo]) ?? collect();
        $cv = $postulacion->candidato?->cvActual;

        return view('livewire.matching.detalle', [
            'postulacion' => $postulacion,
            'resultado' => $resultado,
            'obligatorios' => $tipos->filter(fn (TipoRequisito $t) => $t === TipoRequisito::Obligatorio)->keys()->all(),
            'marcados' => MatchFeedback::where('postulacion_id', $postulacion->id)->where('user_id', $user->id)->pluck('requisito_id')->all(),
            'procesando' => $resultado === null && in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true),
            'puedeEditar' => $user->can('update', $postulacion),
            'otras' => Postulacion::query()
                ->where('vacante_id', $postulacion->vacante_id)
                ->whereNotNull('match_score')
                ->with('candidato')
                ->orderByDesc('match_score')
                ->get()
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
