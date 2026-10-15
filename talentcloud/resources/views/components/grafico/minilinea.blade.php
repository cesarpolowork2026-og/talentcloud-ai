{{--
    Minigráfico de tendencia para un indicador: una línea de 2 px con el último punto marcado. Es solo contexto
    (la cifra y su variación van en texto al lado), por eso es decorativo para lectores de pantalla.

    valores: lista de números (≥ 2 puntos).
--}}
@props(['valores'])

@php
    $n = count($valores);
    $ancho = 120;
    $alto = 34;
    $margen = 4;
    $maximo = max(1, ...$valores);
    $x = fn (int $i): float => $margen + ($n > 1 ? $i * ($ancho - 2 * $margen) / ($n - 1) : 0);
    $y = fn (int|float $v): float => $alto - $margen - ($alto - 2 * $margen) * $v / $maximo;
    $trazo = collect($valores)->map(fn ($v, $i) => ($i === 0 ? 'M' : 'L').round($x($i), 1).' '.round($y($v), 1))->implode(' ');
@endphp

@if ($n >= 2)
    <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" {{ $attributes->class('viz minilinea') }} aria-hidden="true" focusable="false">
        <path d="{{ $trazo }} L{{ round($x($n - 1), 1) }} {{ $alto }} L{{ round($x(0), 1) }} {{ $alto }} Z" fill="var(--viz-s1)" fill-opacity="0.1" />
        <path d="{{ $trazo }}" fill="none" stroke="var(--viz-s1)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="{{ round($x($n - 1), 1) }}" cy="{{ round($y($valores[$n - 1]), 1) }}" r="3.5" fill="var(--viz-s1)" stroke="var(--viz-superficie)" stroke-width="2" />
    </svg>
@endif
