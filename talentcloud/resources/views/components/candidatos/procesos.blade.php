{{--
    Procesos de un candidato agrupados por empresa (vista del admin SaaS, que
    ve candidatos con postulaciones en varias empresas): un resumen
    ("3 procesos en 2 empresas") y, por empresa, cada vacante con su etapa y
    su coincidencia. Recibe las postulaciones con vacante.empresa cargada.
--}}
@props(['postulaciones'])

@php
    $porEmpresa = $postulaciones->groupBy(fn ($p) => $p->vacante?->empresa?->nombre ?? 'Sin empresa')->sortKeys();
    $procesos = $postulaciones->count();
@endphp

<div {{ $attributes->class('grid gap-2 text-13') }}>
    <p class="m-0 font-semibold text-ink">
        {{ $procesos }} {{ $procesos === 1 ? 'proceso' : 'procesos' }} en {{ $porEmpresa->count() }} {{ $porEmpresa->count() === 1 ? 'empresa' : 'empresas' }}
    </p>
    @foreach ($porEmpresa as $empresa => $susProcesos)
        <div class="grid gap-0.5" wire:key="procesos-{{ \Illuminate\Support\Str::slug($empresa) }}">
            <span class="text-12 uppercase tracking-[0.06em] text-accent-700">{{ $empresa }}</span>
            <ul class="m-0 grid list-none gap-0.5 p-0">
                @foreach ($susProcesos as $postulacion)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-2">
                        <span class="text-neutral-800">{{ $postulacion->vacante?->titulo }}</span>
                        <span class="text-neutral-700">{{ $postulacion->estaRetirada() ? 'Retirada' : $postulacion->etapa->etiqueta() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
