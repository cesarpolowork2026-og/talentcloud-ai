<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<x-ui.card class="!p-6">
    <h3 class="mb-4 text-16">Contraseña</h3>

    <form wire:submit="updatePassword" class="contents">
        <div class="grid gap-4">
            <x-ui.field label="Contraseña actual" for="update_password_current_password" error="current_password">
                <x-ui.input wire:model="current_password" id="update_password_current_password" type="password" error="current_password" autocomplete="current-password" />
            </x-ui.field>

            <x-ui.field label="Nueva contraseña" for="update_password_password" error="password">
                <x-ui.input wire:model="password" id="update_password_password" type="password" error="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field label="Confirmar nueva contraseña" for="update_password_password_confirmation" error="password_confirmation">
                <x-ui.input wire:model="password_confirmation" id="update_password_password_confirmation" type="password" error="password_confirmation" autocomplete="new-password" />
            </x-ui.field>
        </div>

        <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Cambiar contraseña</x-ui.button>
        <x-ui.action-message on="password-updated">Contraseña actualizada.</x-ui.action-message>
    </form>
</x-ui.card>
