{{-- Etiqueta de un estado (EstadoVacante, EstadoEntrevista…) con su texto y tono del prototipo. --}}
@props(['valor'])

<x-ui.tag :tone="$valor->tono()" {{ $attributes }}>{{ $valor->etiqueta() }}</x-ui.tag>
