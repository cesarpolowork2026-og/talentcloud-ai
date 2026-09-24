{{-- "Mis postulaciones" del prototipo (TalentCloud App.dc.html, isPostulante). --}}
@use('App\Enums\EstadoDeMiPostulacion')
@use('App\Livewire\Postulante\MisPostulaciones')
@use('App\Support\Formato')
<div>
    <x-ui.page-header title="Mis postulaciones" subtitle="Sigue el estado de tus procesos de selección." />

    <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        @if ($postulaciones->isEmpty())
            <x-ui.empty-state icon="briefcase" message="Todavía no tienes postulaciones." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>Vacante</th><th>Empresa</th><th>Estado</th><th>Fecha</th>
                </x-slot:head>
                @foreach ($postulaciones as $postulacion)
                    <tr class="tc-row" wire:key="mi-postulacion-{{ $postulacion->id }}" wire:click="verDetalle({{ $postulacion->id }})">
                        <td>{{ $postulacion->vacante?->titulo }}</td>
                        <td>{{ $postulacion->vacante?->empresa?->nombre }}</td>
                        <td><x-ui.estado :valor="EstadoDeMiPostulacion::de($postulacion)" /></td>
                        <td class="text-neutral-600">{{ Formato::fechaCorta($postulacion->created_at) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        <div class="grid gap-3">
            <livewire:postulante.mi-curriculo />
            <a href="{{ route('profile') }}" wire:navigate class="text-13 text-neutral-700 hover:text-accent-700">Eliminar mi cuenta y mis datos</a>
        </div>
    </div>

    {{-- Detalle (prototipo: título = vacante) --}}
    <x-ui.dialog wire:model="mostrarDetalle" :title="$seleccionada?->vacante?->titulo" max-width="460px">
        @if ($seleccionada)
            @php($competencias = MisPostulaciones::competenciasIdentificadas($seleccionada))
            <p class="m-0 whitespace-pre-line">{{ $seleccionada->vacante?->empresa?->nombre }}
Estado: {{ EstadoDeMiPostulacion::de($seleccionada)->etiqueta() }}
Postulación enviada el {{ Formato::fechaCorta($seleccionada->created_at) }}.@if ($competencias)

La IA identificó {{ $competencias[0] }} de {{ $competencias[1] }} competencias requeridas en tu CV.@endif</p>
        @endif
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
