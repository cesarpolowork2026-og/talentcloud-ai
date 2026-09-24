<?php

namespace App\Livewire\Dashboard;

use App\Enums\Rol;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "Dashboard" del prototipo (isDashboard): indicadores, vacantes recientes y actividad reciente. */
#[Title('Dashboard')]
class Panel extends Component
{
    public bool $mostrarDetalleDelMatch = false;

    public bool $mostrarVistaPrevia = false;

    #[Locked]
    public ?int $vacanteSeleccionada = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Vacante::class);
    }

    public function verVistaPrevia(int $id, VacanteRepository $vacantes): void
    {
        $vacante = $vacantes->buscar($id);
        abort_if($vacante === null, 404);
        $this->authorize('view', $vacante);

        $this->vacanteSeleccionada = $vacante->id;
        $this->mostrarVistaPrevia = true;
    }

    public function render(DashboardService $dashboard, VacanteRepository $vacantes): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return view('livewire.dashboard.panel', [
            'resumen' => $dashboard->resumen(),
            'diasDelMatch' => DashboardService::DIAS_DEL_MATCH_PROMEDIO,
            'subtitulo' => $usuario->tieneRol(Rol::Admin)
                ? 'Resumen de la actividad de selección en toda la plataforma.'
                : 'Resumen de la actividad de selección de '.$usuario->empresa?->nombre.'.',
            'seleccionada' => $this->vacanteSeleccionada
                ? $vacantes->buscar($this->vacanteSeleccionada)?->load('empresa')->loadCount('postulaciones')
                : null,
        ]);
    }
}
