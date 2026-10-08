<?php

use App\Livewire\Forms\LoginForm;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.presentacion')] #[Title('Iniciar sesión')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: Navegacion::rutaInicio(auth()->user()), navigate: true);
    }
}; ?>

{{--
    Formulario de Login.dc.html, en el diálogo "Iniciar sesión" de la presentación (layouts.presentacion),
    que pone el título. Sin "Ingresar como" (cambio sobre el prototipo): cada cuenta tiene un solo rol.
--}}
<div class="contents">
    <p class="mb-6 text-center text-15 text-neutral-700">Bienvenido a TalentCloud. Ingresa con tu cuenta.</p>

    @if (session('status'))
        <p class="mb-4 text-14 text-accent-700" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="grid gap-4">
        <x-ui.field label="Correo electrónico" for="email" error="form.email">
            <x-ui.input wire:model="form.email" id="email" type="email" placeholder="tu@correo.com" error="form.email" required :autofocus="request()->routeIs('login')" autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="Contraseña" for="password" error="form.password">
            <x-ui.input wire:model="form.password" id="password" type="password" placeholder="••••••••••••" error="form.password" required autocomplete="current-password" />
        </x-ui.field>

        <div class="-mt-1 flex flex-wrap items-center justify-between gap-2">
            <x-ui.checkbox wire:model="form.remember" id="remember" name="remember">Recordarme</x-ui.checkbox>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="whitespace-nowrap text-14">¿Olvidaste tu contraseña?</a>
            @endif
        </div>

        <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Iniciar sesión</x-ui.button>
    </form>

    {{-- Crear cuenta: las dos puertas de entrada, con el mismo peso. --}}
    <p class="mb-0 mt-6 flex items-center gap-3 text-13 text-neutral-700">
        <span class="h-px flex-1 bg-divider" aria-hidden="true"></span>
        ¿No tienes una cuenta?
        <span class="h-px flex-1 bg-divider" aria-hidden="true"></span>
    </p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <x-ui.button :href="route('register')" wire:navigate class="justify-center">
            <x-ui.icon name="buildings" :size="18" />
            Crear empresa
        </x-ui.button>
        <x-ui.button :href="route('register.postulante')" wire:navigate class="justify-center">
            <x-ui.icon name="user-plus" :size="18" />
            Soy postulante
        </x-ui.button>
    </div>

    <div class="mt-6 flex items-start gap-2 border-t border-divider pt-4">
        <x-ui.icon name="lock" :size="15" class="mt-0.5 text-neutral-600" />
        <span class="text-13 leading-[1.5] text-neutral-700">Tus decisiones de selección permanecen bajo control humano.</span>
    </div>
</div>
