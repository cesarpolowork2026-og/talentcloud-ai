<?php

use App\Support\Navegacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Confirmar contraseña')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: Navegacion::rutaInicio(Auth::user()), navigate: true);
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Confirma tu contraseña</h2>
    <p class="mb-6 text-15 text-neutral-700">Es una zona protegida. Escribe tu contraseña para continuar.</p>

    <form wire:submit="confirmPassword" class="grid gap-4">
        <x-ui.field label="Contraseña" for="password" error="password">
            <x-ui.input wire:model="password" id="password" type="password" error="password" required autofocus autocomplete="current-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Confirmar</x-ui.button>
    </form>
</div>
