{{--
    Vista previa de una vacante (diálogo del prototipo al pulsar una fila de
    "Vacantes" o de "Vacantes recientes" en el dashboard).
    vacante: con empresa y postulaciones_count cargados. Acepta wire:model como <x-ui.dialog>.
--}}
@use('App\Support\Formato')
@props(['vacante'])

<x-ui.dialog {{ $attributes }} :title="$vacante?->titulo" max-width="480px">
    @if ($vacante)
        <p class="m-0 whitespace-pre-line">{{ $vacante->empresa?->nombre }} · {{ $vacante->ubicacion ?? $vacante->modalidad->etiqueta() }}
{{ $vacante->postulaciones_count }} {{ $vacante->postulaciones_count === 1 ? 'candidato' : 'candidatos' }} · Estado: {{ $vacante->estado->etiqueta() }}
{{ $vacante->publicada_at ? 'Publicada el '.Formato::fechaCorta($vacante->publicada_at).'.' : 'Sin publicar (borrador).' }}</p>
    @endif
    <x-slot:actions>
        <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
        @if ($vacante)
            <x-ui.button variant="primary" :href="route('vacantes.show', $vacante)" wire:navigate>Ver vacante</x-ui.button>
        @endif
    </x-slot:actions>
</x-ui.dialog>
