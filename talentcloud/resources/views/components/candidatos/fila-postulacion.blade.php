{{-- Fila de una postulación en la ficha del candidato: vacante, etapa (o "Retirada"), fecha y enlace a su detalle. --}}
@props(['postulacion'])

<tr {{ $attributes }}>
    <td><a href="{{ route('vacantes.show', $postulacion->vacante_id) }}" wire:navigate>{{ $postulacion->vacante?->titulo }}</a></td>
    <td>
        @if ($postulacion->estaRetirada())
            <x-ui.tag tone="outline">Retirada</x-ui.tag>
        @else
            <x-ui.estado :valor="$postulacion->etapa" />
        @endif
    </td>
    <td class="text-neutral-600">{{ \App\Support\Formato::fechaCorta($postulacion->created_at) }}</td>
    <td><a href="{{ route('postulaciones.show', $postulacion) }}" wire:navigate>Ver postulación</a></td>
</tr>
