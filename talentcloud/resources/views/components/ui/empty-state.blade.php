{{--
    Estado vacío del prototipo: icono opcional + mensaje centrado; $slot para un botón de acción.
    Primera vez (aún no hay nada): ilustracion (x-ilustracion) + titulo, que invitan a empezar.
    Sin resultados de una búsqueda o un filtro: solo icono y mensaje (no es un momento de bienvenida).
--}}
@props(['message', 'icon' => null, 'ilustracion' => null, 'titulo' => null])

<div {{ $attributes->class('py-8 text-center text-neutral-600') }}>
    @if ($ilustracion)
        <x-ilustracion :nombre="$ilustracion" class="mx-auto w-[220px] max-w-full" />
    @elseif ($icon)
        <x-ui.icon :name="$icon" :size="36" class="mx-auto text-neutral-400" />
    @endif
    @if ($titulo)
        <h2 class="mb-1 mt-4 text-20 text-ink">{{ $titulo }}</h2>
    @endif
    <p @class(['text-15', 'mx-auto max-w-[46ch] text-neutral-700' => $titulo, 'mt-3' => ($icon || $ilustracion) && ! $titulo])>{{ $message }}</p>
    {{ $slot }}
</div>
