<?php

namespace App\Livewire\Reclamaciones;

use App\Livewire\Forms\HojaDeReclamacionForm;
use App\Services\Reclamaciones\RegistrarReclamacion;
use App\Support\Formato;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Libro de Reclamaciones virtual (/libro-de-reclamaciones), público y sin sesión.
 * El formulario lo presenta cualquiera, así que lleva límite de envíos por IP y
 * un campo trampa para bots (los humanos no lo ven ni lo rellenan).
 */
#[Layout('layouts.publico')]
#[Title('Libro de Reclamaciones')]
class Formulario extends Component
{
    public HojaDeReclamacionForm $form;

    /** Campo trampa: oculto para las personas; un bot que lo rellena no registra nada. */
    public string $web = '';

    /** Código de la hoja recién presentada (pantalla de confirmación). */
    #[Locked]
    public ?string $registrada = null;

    #[Locked]
    public ?string $fechaLimite = null;

    #[Locked]
    public ?string $correo = null;

    public function presentar(RegistrarReclamacion $registrar): void
    {
        $clave = 'libro-reclamaciones|'.request()->ip();

        if (RateLimiter::tooManyAttempts($clave, (int) config('talentcloud.reclamaciones.por_hora'))) {
            $this->addError('form.detalle', 'Se enviaron demasiadas hojas desde tu conexión. Inténtalo de nuevo en '.ceil(RateLimiter::availableIn($clave) / 60).' minutos o escríbenos a '.config('talentcloud.soporte.correo').'.');

            return;
        }

        $this->form->validate();

        // La copia va a la dirección escrita: sin este límite, cambiando de IP se podría
        // usar el libro para enviar correos a un tercero una y otra vez.
        $claveCorreo = 'libro-reclamaciones-correo|'.sha1(mb_strtolower(trim($this->form->email)));

        if (RateLimiter::tooManyAttempts($claveCorreo, (int) config('talentcloud.reclamaciones.por_correo_al_dia'))) {
            $this->addError('form.email', 'Ya se enviaron varias hojas a este correo hoy. Si necesitas presentar otra, escríbenos a '.config('talentcloud.soporte.correo').'.');

            return;
        }

        RateLimiter::hit($clave, 3600);
        RateLimiter::hit($claveCorreo, 86400);

        if ($this->web !== '') {
            // Un bot: se le responde como si nada para no darle pistas, pero no se registra.
            $this->form->reset();

            return;
        }

        $reclamacion = $registrar->ejecutar($this->form->datos());

        $this->registrada = $reclamacion->codigo();
        $this->fechaLimite = Formato::fechaLarga($reclamacion->fecha_limite);
        $this->correo = $reclamacion->email;
        $this->form->reset();
    }

    public function render(): View
    {
        return view('livewire.reclamaciones.formulario', [
            'legal' => config('talentcloud.legal'),
            'servicios' => HojaDeReclamacionForm::servicios(),
            'diasHabiles' => (int) config('talentcloud.reclamaciones.dias_habiles'),
        ])->layoutData([
            'descripcion' => 'Libro de Reclamaciones virtual de TalentCloud AI: presenta un reclamo o una queja y recibe la respuesta por correo.',
        ]);
    }
}
