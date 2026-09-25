{{-- Pie de las páginas públicas. --}}
<footer class="border-t border-divider">
    <div class="mx-auto flex max-w-[1480px] flex-wrap items-center gap-x-8 gap-y-3 px-4 py-6 text-13 text-neutral-700 sm:px-8">
        <span class="flex flex-wrap items-center gap-2">
            <x-marca tamano="pequeno" />
            <span>© {{ now()->year }} · Reclutamiento con IA explicable.</span>
        </span>
        <nav aria-label="Pie de página" class="flex flex-wrap gap-6 sm:ml-auto">
            @foreach (['inicio' => 'Inicio', 'caracteristicas' => 'Características', 'beneficios' => 'Beneficios', 'seguridad' => 'Seguridad'] as $ancla => $nombre)
                <a href="{{ route('inicio') }}#{{ $ancla }}">{{ $nombre }}</a>
            @endforeach
            <a href="{{ route('empleos.index') }}">Empleos</a>
        </nav>
    </div>
</footer>
