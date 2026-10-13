{{-- Actividad reciente: se actualiza sola cada 30 s con la pestaña visible. --}}
@use('App\Enums\TipoActividad')
<section aria-labelledby="actividad-titulo" wire:poll.30s.visible>
    <h2 id="actividad-titulo" class="mb-3 text-16">Actividad reciente</h2>
    @forelse ($actividades as $actividad)
        <div class="mb-3 flex gap-2 text-14" wire:key="actividad-{{ $actividad->id }}">
            <x-ui.icon :name="TipoActividad::tryFrom($actividad->tipo)?->icono() ?? 'star-four'" :size="16" class="mt-[3px] text-accent-700" />
            <div class="min-w-0">
                <span class="text-neutral-800">{{ $actividad->descripcion }}</span>
                <span class="block text-12 text-neutral-700">{{ $actividad->created_at?->diffForHumans() }}</span>
            </div>
        </div>
    @empty
        <p class="m-0 text-14 text-neutral-700">Aún no hay actividad. Aquí verás cada movimiento de tus procesos.</p>
    @endforelse
</section>
