{{-- "Mi perfil" del prototipo: datos de la cuenta, contraseña y (solo postulantes) eliminación de la cuenta. --}}
<x-app-layout>
    <x-slot:title>Mi perfil</x-slot:title>

    <div class="max-w-[480px]">
        <x-ui.page-header title="Mi perfil" subtitle="Configuración de tu cuenta." />

        <div class="grid gap-6">
            <livewire:profile.update-profile-information-form />
            <livewire:profile.update-password-form />

            @if (auth()->user()->tieneRol(\App\Enums\Rol::Postulante))
                <livewire:profile.delete-user-form />
            @endif
        </div>
    </div>
</x-app-layout>
