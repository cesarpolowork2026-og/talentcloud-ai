<?php

use Illuminate\Support\Facades\Password;
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

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
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
