<?php

namespace App\Livewire\Planes;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Planes y precios (/planes), página pública del menú junto a Empleos. Los
 * precios salen de PlanEmpresa; no hay pago en línea todavía.
 */
#[Layout('layouts.publico')]
#[Title('Planes y precios')]
class Precios extends Component
{
    public function render(): View
    {
        return view('livewire.planes.precios')->layoutData([
            'descripcion' => 'Planes de TalentCloud AI en soles: empieza gratis con 14 días del plan Pro y paga por vacantes activas, no por usuario.',
        ]);
    }
}
