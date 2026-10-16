<?php

namespace App\Livewire\Plataforma;

use App\Models\MatchFeedback;
use App\Services\Plataforma\CalidadDeLaIa;
use App\Services\Postulaciones\RecalculoDeMatching;
use App\Support\CacheDeResumenes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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
    private const CLAVE_DEL_LOTE = 'calidad-ia.lote';

    public bool $mostrarConfirmacion = false;

    /** Cuántas postulaciones se encolaron en la última acción: el total del avance y del mensaje final. */
    public ?int $encoladas = null;

    public function mount(): void
    {
        $this->authorize('viewAny', MatchFeedback::class);

        // Al volver a la pantalla (o recargarla) mientras el lote sigue en marcha, recupera su total para mostrar el avance.
        $this->encoladas = Cache::get(self::CLAVE_DEL_LOTE);
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

        // Un segundo clic mientras hay trabajo en cola solo duplicaría las llamadas al modelo.
        if ($this->enCola() > 0) {
            $this->addError('rehacer', 'Ya hay análisis en curso. Espera a que terminen para volver a rehacer.');

            return;
        }

        $this->encoladas = $recalculo->encolarBasicos();
        Cache::put(self::CLAVE_DEL_LOTE, $this->encoladas, now()->addHours(6));
    }

    /** Cierra el mensaje de «Listo» una vez leído. */
    public function cerrarResultado(): void
    {
        $this->encoladas = null;
        Cache::forget(self::CLAVE_DEL_LOTE);
    }

    /** Tareas de IA que siguen esperando al worker (la cola «ia» es la de los cálculos de matching). */
    private function enCola(): int
    {
        return Queue::size('ia');
    }

    public function render(CalidadDeLaIa $calidad): View
    {
        $enCola = $this->enCola();

        // Lote terminado: ya no se recupera al recargar, para que unas tareas de IA posteriores y ajenas (un CV nuevo)
        // no se muestren como si fueran el final de este lote. El mensaje «Listo» sigue en esta pantalla hasta cerrarlo.
        if ($enCola === 0 && $this->encoladas !== null) {
            Cache::forget(self::CLAVE_DEL_LOTE);
        }

        return view('livewire.plataforma.calidad-ia', [
            // Con el sondeo de 3 s, las cifras (conteos sobre todas las postulaciones) se recalculan cada 15 s; el avance sale de la cola, en vivo.
            'resumen' => CacheDeResumenes::recordar('calidad-ia', fn () => $calidad->resumen(), 15),
            'dias' => CalidadDeLaIa::DIAS,
            'enCola' => $enCola,
        ]);
    }
}
