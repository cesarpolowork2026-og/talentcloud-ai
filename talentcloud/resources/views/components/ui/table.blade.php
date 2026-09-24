{{--
    Tabla Broadsheet (.table) con desplazamiento horizontal. head: celdas <th>.
    "relative": sin él, un hijo con position:absolute (p. ej. un .sr-only) escapa del recorte y
    ensancha la página en móvil.
--}}
<div class="relative overflow-x-auto">
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
