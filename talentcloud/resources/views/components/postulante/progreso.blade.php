{{--
    Progreso de una postulación para el postulante: Postulado → En revisión → Entrevista → Contratación.
    Solo para los estados en marcha (y Contratado): quien no fue seleccionado, la vacante cerrada o una postulación
    retirada no muestran pasos, porque no se sabe hasta dónde llegó y no se inventa. La Oferta, interna, se le muestra
    como «Entrevista» (ver EstadoDeMiPostulacion). El paso actual lleva aria-current y cada paso dice su estado en
    texto: el color y las marcas no son lo único que informa.

    estado: EstadoDeMiPostulacion. compacto: solo las marcas (para una fila de tabla, donde la etiqueta de estado ya
    lo dice con texto).
--}}
@use('App\Enums\EstadoDeMiPostulacion')
@props(['estado', 'compacto' => false])

@php
    $actual = match ($estado) {
        EstadoDeMiPostulacion::Postulado => 0,
        EstadoDeMiPostulacion::EnRevision => 1,
        EstadoDeMiPostulacion::Entrevista => 2,
        EstadoDeMiPostulacion::Contratado => 3,
        default => null,
    };
    $pasos = ['Postulado', 'En revisión', 'Entrevista', 'Contratación'];
@endphp

@if ($actual !== null)
    <ol {{ $attributes->class(['viz progreso', 'progreso-compacto' => $compacto]) }} @if ($compacto) aria-hidden="true" @else aria-label="Progreso de tu postulación" @endif>
        @foreach ($pasos as $i => $paso)
            @php
                // Contratado: todos los pasos completados (no hay un paso «actual» que seguir esperando).
                $situacion = $i < $actual || $estado === EstadoDeMiPostulacion::Contratado ? 'hecho' : ($i === $actual ? 'actual' : 'pendiente');
            @endphp
            <li class="progreso-paso" data-situacion="{{ $situacion }}" @if ($situacion === 'actual') aria-current="step" @endif>
                <span class="progreso-marca" aria-hidden="true">{{ $situacion === 'hecho' ? '✓' : '' }}</span>
                @unless ($compacto)
                    <span class="progreso-texto">{{ $paso }}</span>
                    <span class="sr-only">{{ match ($situacion) { 'hecho' => ' (completado)', 'actual' => ' (etapa actual)', default => ' (pendiente)' } }}</span>
                @endunless
            </li>
        @endforeach
    </ol>
@endif
