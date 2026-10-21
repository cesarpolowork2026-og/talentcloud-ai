{{--
    "Mi perfil" del prototipo: datos de la cuenta, contraseña y (solo postulantes) sus datos y la eliminación de la cuenta.
    Una tarjeta de resumen arriba (quién eres y dónde) y los formularios en dos columnas desde 768 px, al ancho del resto
    de pantallas: antes era una sola columna de 480 px y quedaba medio ancho vacío. En móvil se apilan.
--}}
@php
    /** @var \App\Models\User $usuario */
    $usuario = auth()->user();
    $esPostulante = $usuario->tieneRol(\App\Enums\Rol::Postulante);
@endphp

<x-app-layout>
    <x-slot:title>Mi perfil</x-slot:title>

    <div>
        <x-ui.page-header title="Mi perfil" subtitle="Configuración de tu cuenta." />

        {{-- Resumen: quién es y a qué empresa pertenece (el rol y la empresa no se editan aquí). --}}
        <x-ui.card class="mb-6 !p-6">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-accent-100 text-24 font-semibold text-accent-700" aria-hidden="true">{{ \App\Support\Navegacion::inicial($usuario) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="m-0 truncate font-heading text-20 font-semibold">{{ $usuario->name }}</p>
                    <p class="m-0 truncate text-14 text-neutral-700">{{ $usuario->email }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.tag tone="accent">{{ $usuario->rol()?->etiquetaPerfil() }}</x-ui.tag>
                    @if ($usuario->empresa)
                        <x-ui.tag tone="outline">{{ $usuario->empresa->nombre }}</x-ui.tag>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <livewire:profile.update-profile-information-form />
            <livewire:profile.update-password-form />
        </div>

        @if ($esPostulante)
            <div class="mt-6 grid items-start gap-6 md:grid-cols-2">
                <x-ui.card class="!p-6">
                    <h2 class="mb-2 text-16">Mis datos</h2>
                    <p class="mb-4 text-14 text-neutral-700">Descarga en un archivo JSON tu cuenta, tu ficha, tus CV y el estado de tus postulaciones.</p>
                    <div>
                        <x-ui.button :href="route('postulante.mis-datos')" download>Descargar mis datos</x-ui.button>
                    </div>
                </x-ui.card>

                <livewire:profile.delete-user-form />
            </div>
        @endif
    </div>
</x-app-layout>
