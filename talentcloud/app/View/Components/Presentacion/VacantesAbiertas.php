<?php

namespace App\View\Components\Presentacion;

use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Support\BolsaPublica;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Sección "Vacantes abiertas" de la presentación: las más recientes de la
 * bolsa pública. Sin vacantes abiertas, la sección no se muestra.
 */
class VacantesAbiertas extends Component
{
    public const CANTIDAD = 6;

    /** @var Collection<int, Vacante> */
    public Collection $vacantes;

    public function __construct(VacanteRepository $repositorio)
    {
        $this->vacantes = BolsaPublica::portada(fn () => $repositorio->publicadasParaListado()->latest('publicada_at')->limit(self::CANTIDAD)->get());
    }

    public function shouldRender(): bool
    {
        return $this->vacantes->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.presentacion.vacantes-abiertas');
    }
}
