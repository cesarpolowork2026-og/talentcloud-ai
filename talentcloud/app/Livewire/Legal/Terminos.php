<?php

namespace App\Livewire\Legal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Términos de uso (/terminos): condiciones del servicio para empresas y postulantes. */
#[Layout('layouts.publico')]
#[Title('Términos de uso')]
class Terminos extends Component
{
    public function render(): View
    {
        return view('livewire.legal.terminos')->layoutData([
            'descripcion' => 'Condiciones de uso de TalentCloud AI para empresas, reclutadores y postulantes.',
        ]);
    }
}
