{{--
    Paginación de la bolsa pública de empleos: enlaces `<a href="?page=N">` reales para que los buscadores recorran
    todas las vacantes sin JavaScript. Con Livewire, el clic sigue sin recargar la página (`gotoPage`); el enlace
    queda como respaldo (clic con la rueda, «abrir en pestaña nueva» y rastreadores).
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Páginas de empleos" class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="m-0 text-13 text-neutral-700">Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</p>

        <ul class="m-0 flex list-none flex-wrap items-center gap-2 p-0">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn-sm opacity-50" aria-disabled="true">Anterior</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0 })" class="btn btn-sm">Anterior</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li aria-hidden="true" class="text-neutral-700">{{ $element }}</li>
                @else
                    @foreach ($element as $pagina => $url)
                        <li wire:key="pagina-{{ $pagina }}">
                            @if ($pagina === $paginator->currentPage())
                                <span class="btn btn-sm btn-primary" aria-current="page">{{ $pagina }}</span>
                            @else
                                <a href="{{ $url }}" wire:click.prevent="gotoPage({{ $pagina }}, '{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0 })" class="btn btn-sm" aria-label="Ir a la página {{ $pagina }}">{{ $pagina }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')" x-on:click="window.scrollTo({ top: 0 })" class="btn btn-sm">Siguiente</a>
                @else
                    <span class="btn btn-sm opacity-50" aria-disabled="true">Siguiente</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
