{{--
    Uso de los límites del plan vigente ("2 de 3 vacantes activas").
    Lo usan "Mi empresa" y el detalle de empresa del admin.
--}}
@props(['uso'])

@php
    $filas = [
        [
            'nombre' => 'Vacantes activas',
            'usado' => $uso->vacantesActivas,
            'limite' => $uso->limiteVacantes(),
            'excede' => $uso->excedeVacantes(),
            'nota' => null,
        ],
        [
            'nombre' => 'Usuarios activos',
            'usado' => $uso->usuariosActivos,
            'limite' => $uso->limiteUsuarios(),
            'excede' => $uso->excedeUsuarios(),
            'nota' => $uso->invitacionesPendientes > 0
                ? $uso->invitacionesPendientes.' '.($uso->invitacionesPendientes === 1 ? 'invitación pendiente ocupa' : 'invitaciones pendientes ocupan').' lugar hasta que se acepte o se revoque.'
                : null,
        ],
    ];
@endphp

<div {{ $attributes->class('grid gap-3') }}>
    @foreach ($filas as $fila)
        <div class="grid gap-1">
            <div class="flex items-baseline justify-between gap-2 text-14">
                <span class="text-neutral-800">{{ $fila['nombre'] }}</span>
                <span class="font-semibold">{{ $fila['limite'] === null ? $fila['usado'].' · sin límite' : $fila['usado'].' de '.number_format($fila['limite'], 0, ',', ' ') }}</span>
            </div>
            @if ($fila['limite'] !== null)
                <x-ui.progress :value="min(100, $fila['usado'] * 100 / $fila['limite'])" :label="$fila['nombre'].': '.$fila['usado'].' de '.$fila['limite']" />
            @endif
            @if ($fila['excede'])
                <p class="m-0 text-13 text-accent-2-700">Supera el límite del plan: se conserva lo existente, pero no se pueden sumar más.</p>
            @endif
            @if ($fila['nota'])
                <p class="m-0 text-13 text-neutral-700">{{ $fila['nota'] }}</p>
            @endif
        </div>
    @endforeach
</div>
