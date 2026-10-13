<?php

namespace App\Livewire\Dashboard;

use App\Enums\Rol;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Plataforma\PanelDePlataforma;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Dashboard": qué requiere atención hoy, cómo van las vacantes, cuánto ahorra la IA y
 * el embudo de selección; la actividad reciente se refresca sola (ActividadReciente).
 * Reemplaza los indicadores del prototipo (candidatos evaluados, match promedio), que
 * informaban pero no ayudaban a decidir qué hacer.
 *
 * Para el admin SaaS es el "Panel de la plataforma" (cambio sobre el
 * prototipo): primero sus empresas cliente y la salud de la IA, y después la
 * actividad de selección de toda la plataforma.
 */
#[Title('Dashboard')]
class Panel extends Component
{
    public bool $mostrarVistaPrevia = false;

    #[Locked]
    public ?int $vacanteSeleccionada = null;

    /** Filtro del embudo: id de una vacante visible, o '' para todas. */
    public string $vacanteDelEmbudo = '';

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

    public function render(DashboardService $dashboard, EmbudoDeSeleccion $embudo, VacanteRepository $vacantes, PanelDePlataforma $panel, LimitesDelPlan $limites): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $esAdmin = $usuario->tieneRol(Rol::Admin);

        // Un id que no es de una vacante visible se trata como "todas".
        $vacanteDelEmbudo = ctype_digit($this->vacanteDelEmbudo) ? $vacantes->buscar((int) $this->vacanteDelEmbudo) : null;

        return view('livewire.dashboard.panel', [
            'plataforma' => $esAdmin ? $panel->resumen() : null,
            'resumen' => $dashboard->resumen(),
            'embudo' => $embudo->calcular($vacanteDelEmbudo?->id),
            'vacantesDelEmbudo' => Vacante::query()
                ->with('empresa:id,nombre')
                ->orderBy('titulo')
                ->get(['id', 'titulo', 'empresa_id'])
                ->mapWithKeys(fn (Vacante $v) => [$v->id => $esAdmin ? $v->titulo.' · '.$v->empresa?->nombre : $v->titulo])
                ->all(),
            // El plan es de la empresa: el admin SaaS ve la plataforma entera y no tiene uno propio.
            'uso' => $esAdmin || $usuario->empresa === null ? null : $limites->uso($usuario->empresa),
            'empresa' => $esAdmin ? null : $usuario->empresa,
            'diasDelAhorro' => DashboardService::DIAS_DEL_AHORRO,
            'diasSinRevisar' => DashboardService::DIAS_SIN_REVISAR,
            'titulo' => $esAdmin ? 'Panel de la plataforma' : 'Dashboard',
            'subtitulo' => $esAdmin
                ? 'Tus empresas cliente, la salud de la IA y la actividad de selección de toda la plataforma.'
                : 'Resumen de la actividad de selección de '.$usuario->empresa?->nombre.'.',
            'seleccionada' => $this->vacanteSeleccionada
                ? $vacantes->buscar($this->vacanteSeleccionada)?->load('empresa')->loadCount('postulaciones')
                : null,
        ])->title($esAdmin ? 'Panel' : 'Dashboard');
    }
}
