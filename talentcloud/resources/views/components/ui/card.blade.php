{{--
    Tarjeta Broadsheet (.card). Solo para ítems discretos, nunca para maquetar la página.
    elev: sm | md | lg
--}}
@props(['kicker' => null, 'title' => null, 'elev' => null])

<div {{ $attributes->class([
    'card',
    match ($elev) {
        'sm' => 'elev-sm',
        'md' => 'elev-md',
        'lg' => 'elev-lg',
        default => null,
    },
]) }}>
    @if ($kicker)
        <div class="card-kicker">{{ $kicker }}</div>
    @endif
    @if ($title)
        <div class="card-title">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>
