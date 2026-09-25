<?php

namespace App\Livewire\Plataforma;

use App\Models\MatchFeedback;
use App\Services\Plataforma\CalidadDeLaIa;
use App\Services\Postulaciones\RecalculoDeMatching;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Calidad de la IA" del admin SaaS: cuánto se analiza con el servicio de IA
 * frente al análisis básico, qué falla y qué resultados cuestionan los
 * reclutadores. Cierra el ciclo de la supervisión humana de la IA.
 */
#[Title('Calidad de la IA')]
class CalidadIa extends Component
{
    public bool $mostrarConfirmacion = false;

    /** Cuántas postulaciones se encolaron en la última acción (para el mensaje). */
    public ?int $encoladas = null;

    public function mount(): void
    {
        $this->authorize('viewAny', MatchFeedback::class);
    }

    public function confirmarRehacer(): void
    {
        $this->authorize('rehacerAnalisis', MatchFeedback::class);

        $this->resetErrorBag();
        $this->mostrarConfirmacion = true;
    }

    public function rehacerBasicos(RecalculoDeMatching $recalculo): void
    {
        $this->authorize('rehacerAnalisis', MatchFeedback::class);

        $this->mostrarConfirmacion = false;

        if (! $recalculo->mejoraLosBasicos()) {
            $this->addError('rehacer', 'El servicio de IA no está habilitado (IA_HABILITADA / MATCHING_ENGINE): rehacer los análisis no mejoraría nada.');

            return;
        }

        $this->encoladas = $recalculo->encolarBasicos();
    }

    public function render(CalidadDeLaIa $calidad): View
    {
        return view('livewire.plataforma.calidad-ia', [
            'resumen' => $calidad->resumen(),
            'dias' => CalidadDeLaIa::DIAS,
        ]);
    }
}
