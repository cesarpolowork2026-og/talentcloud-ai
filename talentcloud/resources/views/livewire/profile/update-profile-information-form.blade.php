<?php

use App\DTO\FichaDelPostulante;
use App\Models\User;
use App\Services\Cuenta\ActualizarPerfil;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    /** Solo se exige al cambiar el correo: quien lo cambia debe conocer la contraseña de la cuenta. */
    public string $password_actual = '';
    /** El correo guardado en la cuenta: la pantalla pide la contraseña solo cuando el escrito es otro. */
    public string $correo_guardado = '';
    public bool $notificaciones_email = true;

    // Ficha de candidato (solo postulantes): lo que ven las empresas junto a su nombre.
    public bool $esPostulante = false;
    public string $titular = '';
    public string $telefono = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = $this->correo_guardado = Auth::user()->email;
        $this->notificaciones_email = Auth::user()->notificaciones_email;

        $candidato = Auth::user()->candidato;
        $this->esPostulante = $candidato !== null;
        $this->titular = (string) $candidato?->titular;
        $this->telefono = (string) $candidato?->telefono;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(ActualizarPerfil $actualizar): void
    {
        /** @var User $user */
        $user = Auth::user();

        $cambiaElCorreo = mb_strtolower(trim($this->email)) !== mb_strtolower($user->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password_actual' => $cambiaElCorreo ? ['required', 'string', 'current_password'] : ['nullable'],
            'notificaciones_email' => ['boolean'],
            'titular' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\s.-]*$/'],
        ], ['telefono.regex' => 'El teléfono solo puede tener números, espacios y los signos + ( ) . -'], ['titular' => 'titular', 'telefono' => 'teléfono', 'password_actual' => 'contraseña actual']);

        $ficha = $user->candidato !== null
            ? new FichaDelPostulante(trim($validated['titular'] ?? '') ?: null, trim($validated['telefono'] ?? '') ?: null)
            : null;

        $actualizar->ejecutar($user, $validated['name'], $validated['email'], $validated['notificaciones_email'], $ficha);

        $this->reset('password_actual');
        $this->correo_guardado = $user->email;

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: Navegacion::rutaInicio($user));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

{{-- Tarjeta de Mi perfil del prototipo: nombre, correo y notificaciones por correo (y, para el postulante, titular y teléfono). --}}
<x-ui.card class="!p-6">
    <h2 class="mb-4 text-16">Datos de la cuenta</h2>

    <form wire:submit="updateProfileInformation" class="contents">
        <div class="grid gap-4">
            <x-ui.field label="Nombre" for="name" error="name">
                <x-ui.input wire:model="name" id="name" error="name" required autocomplete="name" />
            </x-ui.field>

            <x-ui.field label="Correo" for="email" error="email">
                <x-ui.input wire:model="email" id="email" type="email" error="email" required autocomplete="username" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <p class="field-hint">
                        Tu correo no está verificado.
                        <button type="button" wire:click.prevent="sendVerification" class="text-accent-700 hover:underline">Reenviar el enlace de verificación.</button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="field-hint text-accent-700">Enviamos un nuevo enlace de verificación a tu correo.</p>
                    @endif
                @endif
            </x-ui.field>

            {{-- Cambiar el correo cambia a dónde llegan los avisos y la recuperación de la cuenta: se pide la contraseña. --}}
            <div x-show="$wire.email.trim().toLowerCase() !== $wire.correo_guardado.toLowerCase()" x-cloak>
                <x-ui.field label="Contraseña actual" for="password_actual" error="password_actual" hint="La pedimos porque cambiaste el correo. Avisaremos también al correo anterior.">
                    <x-ui.input wire:model="password_actual" id="password_actual" type="password" error="password_actual" autocomplete="current-password" />
                </x-ui.field>
            </div>

            @if ($esPostulante)
                <x-ui.field label="Titular" for="titular" error="titular" hint="Una línea sobre ti que las empresas ven junto a tu nombre.">
                    <x-ui.input wire:model="titular" id="titular" error="titular" placeholder="Desarrollador backend con 5 años en Java" maxlength="120" />
                </x-ui.field>

                <x-ui.field label="Teléfono (opcional)" for="telefono" error="telefono">
                    <x-ui.input wire:model="telefono" id="telefono" type="tel" error="telefono" autocomplete="tel" placeholder="+51 999 999 999" maxlength="40" />
                </x-ui.field>
            @endif

            <x-ui.checkbox wire:model="notificaciones_email" id="notificaciones_email">Recibir notificaciones por correo</x-ui.checkbox>
        </div>

        <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Guardar cambios</x-ui.button>
        <x-ui.action-message on="profile-updated" />
    </form>
</x-ui.card>
