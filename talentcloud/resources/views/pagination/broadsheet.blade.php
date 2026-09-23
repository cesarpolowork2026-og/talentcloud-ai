{{-- Paginación del prototipo para componentes Livewire: "Anterior · Página X de Y · Siguiente". --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="mt-3 flex items-center justify-end gap-3 text-13 text-neutral-600">
        <button
            type="button"
            class="btn btn-ghost"
            wire:click="previousPage('{{ $paginator->getPageName() }}')"
            wire:loading.attr="disabled"
            @disabled($paginator->onFirstPage())
        >Anterior</button>

        <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        <button
            type="button"
            class="btn btn-ghost"
            wire:click="nextPage('{{ $paginator->getPageName() }}')"
            wire:loading.attr="disabled"
            @disabled(! $paginator->hasMorePages())
        >Siguiente</button>
    </nav>
@endif
