{{--
    Una entrevista tal como la ve el postulante: fecha, modalidad, duración,
    estado y enlace de la videollamada. Nunca las notas internas del equipo.
    vacante: "Desarrollador Backend · Nimbus Corp" (en la lista de próximas).
--}}
@use('App\Enums\EstadoEntrevista')
@use('App\Support\EnlaceExterno')
@use('App\Support\Formato')
@props(['entrevista', 'vacante' => null])

@php($enlace = $entrevista->estado !== EstadoEntrevista::Cancelada ? EnlaceExterno::seguro($entrevista->enlace) : null)

<li {{ $attributes->class('grid gap-1 border-b border-divider pb-2 last:border-0 last:pb-0') }}>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-14 font-semibold">{{ Formato::fechaHora($entrevista->inicia_at) }}</span>
        <x-ui.estado :valor="$entrevista->estado" />
    </div>
    @if ($vacante)
        <span class="text-13 text-neutral-800">{{ $vacante }}</span>
    @endif
    <span class="text-13 text-neutral-700">{{ $entrevista->modalidad->etiqueta() }} · {{ $entrevista->duracion_min }} min</span>
    @if ($enlace)
        <a href="{{ $enlace }}" target="_blank" rel="noopener noreferrer" class="text-13">Unirse a la videollamada</a>
    @endif
</li>
