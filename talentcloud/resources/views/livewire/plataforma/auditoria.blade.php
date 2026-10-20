{{-- «Registro de acciones» del admin SaaS. --}}
<div>
    <x-ui.page-header title="Registro de acciones" subtitle="Lo que se hizo sobre empresas, usuarios y competencias, de lo más reciente a lo más antiguo." compact>
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Panel', 'href' => route('dashboard')], ['label' => 'Registro de acciones']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <x-ui.listado-controles placeholder="Buscar en el registro…" />

    @if ($filas->isEmpty())
        <x-ui.empty-state :message="$this->hayFiltros() ? 'No hay acciones con esa búsqueda.' : 'Todavía no se registró ninguna acción.'" />
    @else
        <x-ui.actualizable><x-ui.table>
            <x-slot:head>
                <th>Cuándo</th><th>Quién</th><th>Qué hizo</th><th>Empresa</th>
            </x-slot:head>
            @foreach ($filas as $accion)
                <tr wire:key="auditoria-{{ $accion->id }}">
                    <td class="whitespace-nowrap">{{ \App\Support\Formato::fechaHora($accion->created_at) }}</td>
                    <td>{{ $accion->usuario_nombre }}</td>
                    <td>{{ $accion->descripcion }}</td>
                    <td>{{ $accion->empresa?->nombre ?? '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>

        {{ $filas->links() }}</x-ui.actualizable>
    @endif
</div>
