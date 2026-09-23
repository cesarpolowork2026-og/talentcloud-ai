<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Crear cuenta')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Crear cuenta</h2>
    <p class="mb-6 text-15 text-neutral-700">Registra tu acceso a TalentCloud.</p>

    <form wire:submit="register" class="grid gap-4">
        <x-ui.field label="Nombre" for="name" error="name">
            <x-ui.input wire:model="name" id="name" error="name" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field label="Correo electrónico" for="email" error="email">
            <x-ui.input wire:model="email" id="email" type="email" error="email" required autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="Contraseña" for="password" error="password">
            <x-ui.input wire:model="password" id="password" type="password" error="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field label="Confirmar contraseña" for="password_confirmation" error="password_confirmation">
            <x-ui.input wire:model="password_confirmation" id="password_confirmation" type="password" error="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Crear cuenta</x-ui.button>
    </form>

    <p class="mb-0 mt-4 text-center text-14">¿Ya tienes una cuenta? <a href="{{ route('login') }}" wire:navigate>Iniciar sesión</a></p>
</div>
