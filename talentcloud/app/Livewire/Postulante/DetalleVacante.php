<?php

namespace App\Livewire\Postulante;

use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de una vacante publicada y "Postular con mi CV" (ampliación sobre el
 * prototipo). La vacante se lee por su id con VacanteRepository::buscarPublicada:
 * si no existe o ya no es pública, 404.
 */
class DetalleVacante extends Component
{
    #[Locked]
    public int $vacanteId;

    public bool $mostrarConfirmacion = false;

    /** Recién postulado en esta visita: muestra la confirmación. */
    public bool $postulado = false;

    public function mount(int $id, VacanteRepository $vacantes): void
    {
        $this->authorize('verPublicadas', Vacante::class);

        abort_if($vacantes->buscarPublicada($id) === null, 404);

        $this->vacanteId = $id;
    }

    public function confirmar(): void
    {
        $this->authorize('postular', Vacante::class);

        $this->resetErrorBag();
        $this->mostrarConfirmacion = true;
    }

    public function postular(PostularmeAVacante $postularme): void
    {
        $this->authorize('postular', Vacante::class);

        $this->mostrarConfirmacion = false;

        try {
            $postularme->ejecutar($this->usuario(), $this->vacanteId);
        } catch (OperacionNoPermitida $e) {
            $this->addError('postulacion', $e->getMessage());

            return;
        }

        $this->postulado = true;
    }

    public function render(VacanteRepository $vacantes): View
    {
        // Null si dejó de ser pública mientras el postulante la miraba: la vista lo explica.
        $vacante = $vacantes->buscarPublicada($this->vacanteId);
        $candidato = $this->usuario()->candidato;
        $suPostulacion = $candidato?->postulacionesEnTodasLasEmpresas()->where('vacante_id', $this->vacanteId)->first(['id', 'retirada_at']);

        return view('livewire.postulante.detalle-vacante', [
            'vacante' => $vacante,
            'cv' => $candidato?->cvActual,
            'yaPostulo' => $suPostulacion !== null,
            'laRetiro' => $suPostulacion?->estaRetirada() ?? false,
        ])->title($vacante->titulo ?? 'Vacante');
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
