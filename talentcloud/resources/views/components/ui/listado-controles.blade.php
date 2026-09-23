{{--
    Barra de controles de un listado del prototipo: búsqueda, chips de estado
    y "Ordenar por" con dirección. Va dentro de un componente con WithListado.
--}}
@props([
    'placeholder' => 'Buscar…',
    'estados' => [],
    'estadoActual' => '',
    'ordenes' => [],
    'direccion' => 'asc',
])

<div {{ $attributes->class('mb-4 flex flex-wrap items-center gap-3') }}>
    <x-ui.input
        type="search"
        class="max-w-[220px]"
        :placeholder="$placeholder"
        aria-label="{{ $placeholder }}"
        wire:model.live.debounce.300ms="busqueda"
    />

    @if ($estados)
        <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
            @foreach (['' => 'Todas'] + $estados as $valor => $etiqueta)
                <button
                    type="button"
                    wire:click="filtrarEstado('{{ $valor }}')"
                    @class(['tag', 'tag-accent' => $estadoActual === (string) $valor, 'tag-outline' => $estadoActual !== (string) $valor])
                    aria-pressed="{{ $estadoActual === (string) $valor ? 'true' : 'false' }}"
                >{{ $etiqueta }}</button>
            @endforeach
        </div>
    @endif

    @if ($ordenes)
        <div class="ml-auto flex items-center gap-2">
            <label for="listado-orden" class="text-13 text-neutral-600">Ordenar por</label>
            <x-ui.select id="listado-orden" :options="$ordenes" wire:model.live="orden" />
            <x-ui.button
                variant="ghost"
                icon
                wire:click="alternarDireccion"
                aria-label="{{ $direccion === 'asc' ? 'Orden ascendente; cambiar a descendente' : 'Orden descendente; cambiar a ascendente' }}"
            >{{ $direccion === 'asc' ? '↑' : '↓' }}</x-ui.button>
        </div>
    @endif
</div>
