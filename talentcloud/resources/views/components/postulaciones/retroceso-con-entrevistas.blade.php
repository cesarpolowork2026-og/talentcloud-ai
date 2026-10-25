{{--
    Pregunta al devolver antes de «Entrevista» a quien tiene entrevistas por realizar (PreguntaSiCancelarEntrevistas):
    cancelarlas o mantenerlas. La pantalla define confirmarRetroceso(bool $cancelar).
--}}
@props(['retroceso'])

@php
    $cuantas = (int) ($retroceso['cuantas'] ?? 1);
    $una = $cuantas === 1;
@endphp

<x-ui.dialog wire:model="mostrarRetroceso" :title="$una ? '¿Cancelar la entrevista?' : '¿Cancelar las entrevistas?'" max-width="480px">
    @if ($retroceso)
        <p class="m-0">
            {{ $retroceso['nombre'] }} tiene {{ App\Services\Entrevistas\EntrevistasPorRealizar::texto($cuantas) }} por realizar. Vas a moverla a
            «{{ App\Enums\EtapaPostulacion::from($retroceso['destino'])->etiqueta() }}»: ¿{{ $una ? 'la cancelas o la mantienes' : 'las cancelas o las mantienes' }}?
        </p>
        <p class="m-0 mt-3 text-14 text-neutral-700">
            {{ $una ? 'Si la cancelas, se avisa' : 'Si las cancelas, se avisa' }} al candidato y al entrevistador.
            {{ $una ? 'Si la mantienes, sigue agendada como está.' : 'Si las mantienes, siguen agendadas como están.' }}
        </p>
    @endif

    <x-slot:actions>
        <x-ui.button x-on:click="open = false">No mover</x-ui.button>
        <x-ui.button wire:click="confirmarRetroceso(false)" wire:loading.attr="disabled" wire:target="confirmarRetroceso">{{ $una ? 'Mover y mantenerla' : 'Mover y mantenerlas' }}</x-ui.button>
        <x-ui.button variant="danger" wire:click="confirmarRetroceso(true)" wire:loading.attr="disabled" wire:target="confirmarRetroceso">{{ $una ? 'Mover y cancelarla' : 'Mover y cancelarlas' }}</x-ui.button>
    </x-slot:actions>
</x-ui.dialog>
