<?php

use App\Models\User;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public bool $notificaciones_email = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->notificaciones_email = Auth::user()->notificaciones_email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'notificaciones_email' => ['boolean'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

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

{{-- Tarjeta de Mi perfil del prototipo: nombre, correo y notificaciones por correo. --}}
<x-ui.card class="!p-6">
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

            <x-ui.checkbox wire:model="notificaciones_email" id="notificaciones_email">Recibir notificaciones por correo</x-ui.checkbox>
        </div>

        <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Guardar cambios</x-ui.button>
        <x-ui.action-message on="profile-updated" />
    </form>
</x-ui.card>
