<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Recuperar contraseña')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Límite por IP: nadie envía correos de recuperación en masa a direcciones ajenas.
        $clave = 'recuperar-contrasena|'.request()->ip();

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $this->addError('email', 'Demasiadas solicitudes. Inténtalo de nuevo en '.ceil(RateLimiter::availableIn($clave) / 60).' minutos.');

            return;
        }

        RateLimiter::hit($clave, 600);

        // Se envía si la cuenta existe, pero la respuesta es siempre la misma: no revela qué
        // correos están registrados (tampoco la espera entre envíos, que solo tienen las cuentas reales).
        Password::sendResetLink($this->only('email'));

        $this->reset('email');

        session()->flash('status', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.');
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Recuperar contraseña</h2>
    <p class="mb-6 text-15 text-neutral-700">Escribe tu correo y te enviaremos un enlace para crear una nueva contraseña.</p>

    @if (session('status'))
        <p class="mb-4 text-14 text-accent-700" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="sendPasswordResetLink" class="grid gap-4">
        <x-ui.field label="Correo electrónico" for="email" error="email">
            <x-ui.input wire:model="email" id="email" type="email" placeholder="reclutador@empresa.com" error="email" required autofocus />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Enviar enlace</x-ui.button>
    </form>

    <p class="mb-0 mt-4 text-center text-14"><a href="{{ route('login') }}" wire:navigate>Volver a iniciar sesión</a></p>
</div>
