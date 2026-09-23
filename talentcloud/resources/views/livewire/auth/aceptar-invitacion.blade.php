<div class="contents">
    @if ($valida)
        <h2 class="mb-2 text-28">Únete a {{ $empresa }}</h2>
        <p class="mb-6 text-15 text-neutral-700">Te invitaron como {{ mb_strtolower($rol) }}. Crea tu cuenta para empezar.</p>

        <form wire:submit="aceptar" class="grid gap-4">
            <x-ui.field label="Correo electrónico" for="email">
                <x-ui.input id="email" type="email" :value="$email" disabled />
            </x-ui.field>

            <x-ui.field label="Nombre" for="name" error="name">
                <x-ui.input wire:model="name" id="name" error="name" required autofocus autocomplete="name" />
            </x-ui.field>

            <x-ui.field label="Contraseña" for="password" error="password">
                <x-ui.input wire:model="password" id="password" type="password" error="password" required autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field label="Confirmar contraseña" for="password_confirmation" error="password_confirmation">
                <x-ui.input wire:model="password_confirmation" id="password_confirmation" type="password" error="password_confirmation" required autocomplete="new-password" />
            </x-ui.field>

            <x-ui.button type="submit" variant="primary" block wire:loading.attr="disabled">Crear mi cuenta</x-ui.button>
        </form>
    @else
        <h2 class="mb-2 text-28">Invitación no válida</h2>
        <p class="mb-4 text-15 text-neutral-700">Este enlace ya se usó o venció. Pide a tu empresa que te envíe una nueva invitación.</p>
        @error('name')
            <p class="field-error" role="alert">{{ $message }}</p>
        @enderror
        <p class="m-0 text-14"><a href="{{ route('login') }}" wire:navigate>Ir a iniciar sesión</a></p>
    @endif
</div>
