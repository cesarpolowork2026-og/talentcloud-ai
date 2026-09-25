<?php

use App\DTO\RegistroPostulanteData;
use App\Models\User;
use App\Services\Registro\RegistrarPostulante;
use App\Support\Navegacion;
use App\Support\LimiteDeRegistros;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Registro de postulante')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $consentimiento = false;

    public function register(RegistrarPostulante $registrar): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'consentimiento' => ['accepted'],
        ], [
            'consentimiento.accepted' => 'Debes aceptar el tratamiento de tus datos para registrarte.',
        ]);

        LimiteDeRegistros::contar('email');

        $user = $registrar->ejecutar(new RegistroPostulanteData(
            nombre: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            aceptaTratamientoDeDatos: true,
        ));

        Auth::login($user);

        // Si llegó desde "Postular" en la bolsa pública, vuelve a esa vacante (empleos.postular).
        $this->redirectIntended(default: Navegacion::rutaInicio($user), navigate: true);
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Regístrate como postulante</h2>
    <p class="mb-6 text-15 text-neutral-700">Postula a vacantes y sigue el estado de tus procesos.</p>

    <form wire:submit="register" class="grid gap-4">
        <x-ui.field label="Nombre completo" for="name" error="name">
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

        <x-ui.field error="consentimiento">
            <x-ui.checkbox wire:model="consentimiento" id="consentimiento">
                Acepto que TalentCloud trate mis datos y analice mi CV con IA para mis postulaciones. Puedo eliminar mi cuenta y mis datos cuando quiera.
            </x-ui.checkbox>
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Crear cuenta</x-ui.button>
    </form>

    <p class="mb-0 mt-4 text-center text-14">¿Ya tienes una cuenta? <a href="{{ route('login') }}" wire:navigate>Iniciar sesión</a></p>
</div>
