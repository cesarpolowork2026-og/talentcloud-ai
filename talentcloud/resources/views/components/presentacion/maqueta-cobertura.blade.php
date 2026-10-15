{{-- Maqueta: mapa de calor de cobertura de requisitos (candidatos × requisitos), con datos ficticios fijos. --}}
@php
    $requisitos = [['Java', true], ['Spring Boot', true], ['PostgreSQL', true], ['Docker', false], ['AWS', false], ['Inglés', false]];
    // 1 = respaldado, 2 = conviene revisarlo, 3 = no aparece
    $candidatos = [
        ['Rosa Paredes', 'Desarrolladora Backend', 85, [1, 1, 1, 1, 3, 1]],
        ['Luis Campos', 'Ingeniero de Software', 78, [1, 1, 2, 1, 1, 2]],
        ['Marta Quispe', 'Analista de Sistemas', 71, [1, 2, 1, 3, 2, 1]],
        ['Diego Salas', 'Desarrollador Java', 64, [1, 2, 2, 1, 3, 3]],
    ];
    $clase = [1 => 'mapa-coincide', 2 => 'mapa-revisar', 3 => 'mapa-no'];
    $simbolo = [1 => '✓', 2 => '?', 3 => '✗'];
@endphp
<x-presentacion.marco activo="Matching">
    <p class="mb-1 font-heading text-22 font-semibold">Cobertura de requisitos</p>
    <p class="mb-3 text-12 text-neutral-700">Desarrollador Backend @ Nimbus Corp · los 4 primeros del ranking frente a lo que pide la vacante.</p>

    <div class="card elev-md !bg-white">
        <div class="viz mapa [&_.mapa-columna_span]:max-h-[88px]">
            <ul class="mapa-leyenda mb-2">
                <li><span class="mapa-celda mapa-coincide">✓</span> Respaldado por el CV</li>
                <li><span class="mapa-celda mapa-revisar">?</span> Conviene revisarlo</li>
                <li><span class="mapa-celda mapa-no">✗</span> No aparece</li>
            </ul>
            <table>
                <thead>
                    <tr>
                        <th class="mapa-fila-titulo text-12 text-neutral-700">Candidato</th>
                        @foreach ($requisitos as [$nombre, $obligatorio])
                            <th class="mapa-columna" @if ($obligatorio) data-obligatorio @endif><span>{{ $nombre }}{{ $obligatorio ? '*' : '' }}</span></th>
                        @endforeach
                        <th class="mapa-porcentaje text-12 text-neutral-700">Coincidencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($candidatos as [$nombre, $titular, $porcentaje, $niveles])
                        <tr>
                            <th class="mapa-fila-titulo"><span class="font-semibold text-accent-700">{{ $nombre }}</span><small>{{ $titular }}</small></th>
                            @foreach ($niveles as $nivel)
                                <td class="mapa-celda {{ $clase[$nivel] }}">{{ $simbolo[$nivel] }}</td>
                            @endforeach
                            <td class="mapa-porcentaje">{{ $porcentaje }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-presentacion.marco>
