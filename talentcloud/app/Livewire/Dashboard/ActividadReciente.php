<?php

namespace App\Livewire\Dashboard;

use App\Models\Actividad;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «Actividad reciente» del dashboard. Se refresca sola cada pocos segundos mientras la
 * pestaña está a la vista (sondeo, sin websockets) y por separado del resto de la página:
 * cada refresco hace una sola consulta.
 */
class ActividadReciente extends Component
{
    public const CANTIDAD = 8;

    public function render(): View
    {
        return view('livewire.dashboard.actividad-reciente', [
            'actividades' => Actividad::query()->latest()->limit(self::CANTIDAD)->get(),
        ]);
    }
}
