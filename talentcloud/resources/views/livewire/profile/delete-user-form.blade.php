<?php

use App\Enums\Rol;
use App\Livewire\Actions\Logout;
use App\Services\Cuenta\EliminarCuentaDePostulante;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    public bool $confirmando = false;

    /**
     * Elimina la cuenta del postulante autenticado. Las cuentas de empresa y
     * de reclutador las gestiona su empresa; la del admin, la plataforma.
     */
    public function deleteUser(Logout $logout, EliminarCuentaDePostulante $eliminar): void
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless($user->tieneRol(Rol::Postulante), 403);

        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $logout();
        $eliminar->ejecutar($user);

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<x-ui.card class="!p-6">
    <h3 class="mb-2 text-16">Eliminar mi cuenta</h3>
    <p class="mb-4 text-14 text-neutral-700">Se eliminarán tu cuenta, tu CV y tus postulaciones. Esta acción no se puede deshacer.</p>

    <div>
        <x-ui.button variant="danger" wire:click="$set('confirmando', true)">Eliminar mi cuenta</x-ui.button>
    </div>

    <x-ui.dialog wire:model="confirmando" title="Eliminar mi cuenta" max-width="420px">
        <form wire:submit="deleteUser" id="form-eliminar-cuenta" class="grid gap-3">
            <p class="m-0">Escribe tu contraseña para confirmar que quieres eliminar tu cuenta de forma permanente.</p>
            <x-ui.field label="Contraseña" for="password_eliminar" error="password">
                <x-ui.input wire:model="password" id="password_eliminar" type="password" error="password" autocomplete="current-password" />
            </x-ui.field>
        </form>

        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-eliminar-cuenta" variant="danger" wire:loading.attr="disabled">Eliminar cuenta</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</x-ui.card>
