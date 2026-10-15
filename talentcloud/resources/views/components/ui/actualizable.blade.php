{{--
    Zona que se vuelve a pintar tras una acción de la persona (buscar, filtrar, ordenar, cambiar de página o de periodo):
    mientras llega la respuesta (pasados 200 ms: lo rápido no parpadea) se atenúa y no admite clics, y los lectores de
    pantalla oyen «Actualizando…». No cambia el tamaño: nada salta. Para las listas con WithListado basta el valor por defecto.

    target: nombres de las propiedades o métodos de Livewire que la actualizan (separados por coma).
--}}
@props(['target' => 'busqueda,estado,orden,direccion,gotoPage,nextPage,previousPage,setPage'])

<div {{ $attributes }} wire:loading.class.delay="tc-actualizando" wire:target="{{ $target }}">
    {{ $slot }}
    <span class="sr-only" role="status" wire:loading.delay wire:target="{{ $target }}">Actualizando…</span>
</div>
