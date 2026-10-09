<?php

use App\DTO\RegistroEmpresaData;
use App\Models\User;
use App\Services\Registro\RegistrarEmpresa;
use App\Support\LimiteDeRegistros;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Crear empresa')] class extends Component
{
    public string $empresa = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * "Crear empresa" (enlace del login): alta de la empresa y de su cuenta.
     */
    public function register(RegistrarEmpresa $registrar): void
    {
        $validated = $this->validate([
            'empresa' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], attributes: ['empresa' => 'nombre de la empresa', 'name' => 'tu nombre']);

        LimiteDeRegistros::contar('email');

        $user = $registrar->ejecutar(new RegistroEmpresaData(
            nombreEmpresa: $validated['empresa'],
            nombre: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
        ));

        Auth::login($user);

        // Como en el prototipo (?onboarding=1): la empresa nueva empieza por el checklist.
        $this->redirect(route('onboarding', absolute: false), navigate: true);
    }
}; ?>

<div class="contents">
    <h2 class="mb-2 text-28">Crear empresa</h2>
    <p class="mb-6 text-15 text-neutral-700">Registra tu empresa y gestiona tus procesos de selección.</p>

    <form wire:submit="register" class="grid gap-4">
        <x-ui.field label="Nombre de la empresa" for="empresa" error="empresa">
            <x-ui.input wire:model="empresa" id="empresa" placeholder="Nimbus Corp" error="empresa" required autofocus autocomplete="organization" />
        </x-ui.field>

        <x-ui.field label="Tu nombre" for="name" error="name">
            <x-ui.input wire:model="name" id="name" error="name" required autocomplete="name" />
        </x-ui.field>

        <x-ui.field label="Correo electrónico" for="email" error="email">
            <x-ui.input wire:model="email" id="email" type="email" placeholder="admin@empresa.com" error="email" required autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="Contraseña" for="password" error="password">
            <x-ui.input wire:model="password" id="password" type="password" error="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field label="Confirmar contraseña" for="password_confirmation" error="password_confirmation">
            <x-ui.input wire:model="password_confirmation" id="password_confirmation" type="password" error="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <p class="m-0 text-13 text-neutral-700">Al crear la cuenta aceptas los <a href="{{ route('terminos') }}" target="_blank" rel="noopener">Términos de uso</a> y la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">Política de privacidad</a>.</p>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Crear empresa</x-ui.button>
    </form>

    <p class="mb-0 mt-4 text-center text-14">¿Ya tienes una cuenta? <a href="{{ route('login') }}" wire:navigate>Iniciar sesión</a></p>
    <p class="m-0 text-center text-14">¿Buscas empleo? <a href="{{ route('register.postulante') }}" wire:navigate>Regístrate como postulante</a></p>
</div>
