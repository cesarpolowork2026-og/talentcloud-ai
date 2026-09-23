<?php

use App\Livewire\Actions\Logout;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Verificar correo')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: Navegacion::rutaInicio(Auth::user()), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Verifica tu correo</h2>
    <p class="mb-6 text-15 text-neutral-700">Te enviamos un enlace de verificación. Ábrelo para activar tu cuenta; si no lo encuentras, podemos enviarte otro.</p>

    @if (session('status') == 'verification-link-sent')
        <p class="mb-4 text-14 text-accent-700" role="status">Enviamos un nuevo enlace de verificación a tu correo.</p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.button variant="primary" wire:click="sendVerification" wire:loading.attr="disabled">Reenviar enlace</x-ui.button>
        <x-ui.button variant="ghost" wire:click="logout">Cerrar sesión</x-ui.button>
    </div>
</div>
