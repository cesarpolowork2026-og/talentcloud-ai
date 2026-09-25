{{-- "Notas del equipo" de una postulación. Internas: nunca las ve el postulante ni otra empresa. --}}
@use('App\Support\Formato')
<div class="grid gap-3">
    <form wire:submit="agregar" class="grid gap-2">
        <x-ui.field label="Nueva nota" for="nota-{{ $postulacionId }}" error="texto">
            <x-ui.textarea wire:model="texto" id="nota-{{ $postulacionId }}" rows="2" placeholder="Buena comunicación, pedir referencias…" error="texto" />
        </x-ui.field>
        <div>
            <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="agregar">Guardar nota</x-ui.button>
        </div>
    </form>

    @if ($notas->isEmpty())
        <p class="m-0 text-13 text-neutral-700">Todavía no hay notas del equipo.</p>
    @else
        <ul class="m-0 grid list-none gap-2 p-0" aria-label="Notas del equipo">
            @foreach ($notas as $nota)
                <li class="grid gap-1 border-b border-divider pb-2 last:border-0" wire:key="nota-{{ $nota->id }}">
                    <div class="flex items-baseline justify-between gap-2 text-12 text-neutral-700">
                        <span><strong class="font-semibold text-ink">{{ $nota->autor?->name ?? 'Usuario eliminado' }}</strong> · {{ Formato::fechaHora($nota->created_at) }}</span>
                        @if ($nota->autor_id === $usuarioId)
                            <button type="button" wire:click="borrar({{ $nota->id }})" wire:confirm="¿Borrar esta nota?" class="min-h-6 text-12 text-accent-2-700 hover:underline" aria-label="Borrar la nota del {{ Formato::fechaHora($nota->created_at) }}">Borrar</button>
                        @endif
                    </div>
                    <p class="m-0 whitespace-pre-line text-14 text-neutral-800">{{ $nota->texto }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
