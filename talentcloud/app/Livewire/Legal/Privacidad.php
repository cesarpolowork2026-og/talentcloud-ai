<?php

namespace App\Livewire\Legal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Política de privacidad (/privacidad), Ley 29733. La enlazan el pie y la
 * casilla de consentimiento del registro de postulante: el consentimiento debe
 * ser informado. Describe lo que la aplicación hace de verdad; si cambia el
 * tratamiento de datos, cambia este texto (y config('talentcloud.legal.actualizado')).
 */
#[Layout('layouts.publico')]
#[Title('Política de privacidad')]
class Privacidad extends Component
{
    public function render(): View
    {
        return view('livewire.legal.privacidad')->layoutData([
            'descripcion' => 'Qué datos trata TalentCloud AI, para qué, quién los ve, cuánto tiempo se conservan y cómo ejercer tus derechos.',
        ]);
    }
}
