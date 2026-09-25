{{-- "Mi perfil" del prototipo: datos de la cuenta, contraseña y (solo postulantes) eliminación de la cuenta. --}}
<x-app-layout>
    <x-slot:title>Mi perfil</x-slot:title>

    <div class="max-w-[480px]">
        <x-ui.page-header title="Mi perfil" subtitle="Configuración de tu cuenta." />

        <div class="grid gap-6">
            <livewire:profile.update-profile-information-form />
            <livewire:profile.update-password-form />

            @if (auth()->user()->tieneRol(\App\Enums\Rol::Postulante))
                <x-ui.card class="!p-6">
                    <h2 class="mb-2 text-16">Mis datos</h2>
                    <p class="mb-4 text-14 text-neutral-700">Descarga en un archivo JSON tu cuenta, tu ficha, tus CV, las competencias que la IA identificó y el estado de tus postulaciones.</p>
                    <div>
                        <x-ui.button :href="route('postulante.mis-datos')" download>Descargar mis datos</x-ui.button>
                    </div>
                </x-ui.card>

                <livewire:profile.delete-user-form />
            @endif
        </div>
    </div>
</x-app-layout>
