<?php

namespace App\Livewire\Postulante;

use App\Enums\EstadoEntrevista;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Postulaciones\RetirarPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\EmpresaScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Mis postulaciones" del prototipo (isPostulante). El postulante no tiene
 * contexto de empresa: sus postulaciones se leen sin ese filtro, siempre a
 * través de su propio perfil de candidato.
 */
#[Title('Mis postulaciones')]
class MisPostulaciones extends Component
{
    public bool $mostrarDetalle = false;

    public bool $mostrarRetiro = false;

    #[Locked]
    public ?int $postulacionSeleccionada = null;

    public function verDetalle(int $id): void
    {
        abort_unless($this->consulta()?->whereKey($id)->exists(), 404);

        $this->postulacionSeleccionada = $id;
        $this->mostrarDetalle = true;
    }

    public function confirmarRetiro(): void
    {
        $this->authorize('retirar', $this->seleccionada());

        $this->resetErrorBag();
        // Un diálogo a la vez: el del detalle atraparía el foco del de confirmación.
        $this->mostrarDetalle = false;
        $this->mostrarRetiro = true;
    }

    public function retirar(RetirarPostulacion $retirar): void
    {
        $postulacion = $this->seleccionada();
        $this->authorize('retirar', $postulacion);

        $this->mostrarRetiro = false;
        // Vuelve al detalle, que muestra el resultado (estado "Retirada" o el motivo).
        $this->mostrarDetalle = true;

        try {
            /** @var User $usuario */
            $usuario = auth()->user();
            $retirar->ejecutar($postulacion, $usuario);
        } catch (OperacionNoPermitida $e) {
            $this->addError('retiro', $e->getMessage());

            return;
        }

        $this->dispatch('postulacion-retirada');
    }

    public function render(EntrevistaRepository $entrevistasRepo): View
    {
        $postulaciones = $this->postulaciones();
        /** @var User $usuario */
        $usuario = auth()->user();
        $entrevistas = $usuario->candidato ? $entrevistasRepo->delCandidato($usuario->candidato) : collect();

        return view('livewire.postulante.mis-postulaciones', [
            'postulaciones' => $postulaciones,
            // Solo la postulación abierta en el detalle se trae completa.
            'seleccionada' => $this->postulacionSeleccionada !== null && ($this->mostrarDetalle || $this->mostrarRetiro) ? $this->seleccionada() : null,
            'entrevistasPorPostulacion' => $entrevistas->groupBy('postulacion_id'),
            // Próximas: pendientes o confirmadas y aún por celebrarse.
            'proximas' => $entrevistas
                ->filter(fn (Entrevista $e) => in_array($e->estado, [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada], true) && $e->inicia_at->isFuture())
                ->values(),
        ]);
    }

    /** La postulación abierta en el detalle, completa; siempre una del propio postulante. */
    private function seleccionada(): Postulacion
    {
        $postulacion = $this->consulta()?->find($this->postulacionSeleccionada);

        abort_if($postulacion === null, 404);

        return $postulacion;
    }

    /**
     * La lista, solo con las columnas que pinta.
     *
     * @return Collection<int, Postulacion>
     */
    private function postulaciones(): Collection
    {
        return $this->consulta()
            ?->select(['postulaciones.id', 'postulaciones.empresa_id', 'postulaciones.vacante_id', 'postulaciones.candidato_id', 'postulaciones.etapa', 'postulaciones.created_at', 'postulaciones.retirada_at', 'postulaciones.estado_avisado'])
            ->latest()
            ->get() ?? collect();
    }

    /** @return HasMany<Postulacion, Candidato>|null */
    private function consulta(): ?HasMany
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario->candidato?->postulacionesEnTodasLasEmpresas()
            ->with(['vacante' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)->with('empresa')]);
    }
}
