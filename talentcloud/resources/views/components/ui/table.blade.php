{{-- Tabla Broadsheet (.table) con desplazamiento horizontal. head: celdas <th>. --}}
<div class="overflow-x-auto">
    <table {{ $attributes->class('table') }}>
        @isset($head)
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
        @endisset
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
