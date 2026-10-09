{{--
    Documento legal (privacidad, términos): título, fecha de actualización, datos del
    responsable (config talentcloud.legal; RUC y domicilio solo si están definidos) y el texto.
--}}
@props(['titulo'])

@php
    $legal = config('talentcloud.legal');
    $actualizado = \Illuminate\Support\Carbon::parse($legal['actualizado']);
@endphp

<article class="legal max-w-[75ch]">
    <x-ui.page-header :title="$titulo" :subtitle="'Última actualización: '.$actualizado->day.' de '.$actualizado->locale('es')->monthName.' de '.$actualizado->year.'.'" compact />

    <div class="mb-8 rounded border border-divider bg-surface p-4 text-14">
        <p class="m-0"><strong>Responsable:</strong> {{ $legal['responsable'] }}@if ($legal['ruc']) · RUC {{ $legal['ruc'] }}@endif</p>
        @if ($legal['domicilio'])
            <p class="m-0">Domicilio: {{ $legal['domicilio'] }}</p>
        @endif
        @if ($legal['contacto'])
            <p class="m-0">Contacto: <a href="mailto:{{ $legal['contacto'] }}">{{ $legal['contacto'] }}</a></p>
        @endif
    </div>

    {{ $slot }}
</article>
