<?php

namespace App\Livewire\Postulante;

use App\Enums\Modalidad;
use App\Livewire\Concerns\WithListado;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Vacantes" del postulante (ampliación sobre el prototipo): las vacantes
 * abiertas de todas las empresas activas, de la más reciente a la más antigua,
 * con búsqueda y filtro por modalidad.
 */
#[Title('Vacantes')]
class Vacantes extends Component
{
    use WithListado;

    public function mount(): void
    {
        $this->authorize('verPublicadas', Vacante::class);
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['titulo', 'ubicacion', 'empresa.nombre'];
    }

    /**
     * Los chips de filtro son las modalidades.
     *
     * @return array<string, string>
     */
    protected function opcionesEstado(): array
    {
        return Modalidad::opciones();
    }

    protected function columnaEstado(): string
    {
        return 'modalidad';
    }

    protected function porPagina(): int
    {
        return 12;
    }

    public function render(VacanteRepository $vacantes): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.postulante.vacantes', [
            'filas' => $this->listar($vacantes->publicadas()->latest('publicada_at')),
            'modalidades' => Modalidad::opciones(),
            'postuladas' => $user->candidato?->postulacionesEnTodasLasEmpresas()->pluck('vacante_id')->all() ?? [],
        ]);
    }
}
