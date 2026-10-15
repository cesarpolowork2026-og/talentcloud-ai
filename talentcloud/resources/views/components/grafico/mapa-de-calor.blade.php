{{--
    Mapa de calor de cobertura: candidatos en filas, requisitos de la vacante en columnas y, en cada celda, qué tan
    respaldado está ese requisito por el CV. Sirve para comparar perfiles de un vistazo y ver dónde falla cada uno.

    El nivel es ordinal (más oscuro = mejor respaldado) y además lleva su símbolo (✓ ? ✗), así que el color no va solo.
    Es una tabla real: los lectores de pantalla la recorren por filas y columnas. Resume lo que ya explica el matching;
    no decide nada.

    columnas: lista de ['competencia', 'obligatorio'].
    filas: lista de ['clave', 'titulo', 'subtitulo', 'url', 'porcentaje' (int|null), 'niveles' (lista de NivelCoincidencia|null,
           una por columna, en el mismo orden)].
--}}
@use('App\Enums\NivelCoincidencia')
@props(['columnas', 'filas', 'rotulo' => 'Cobertura de los requisitos de la vacante por candidato'])

<div {{ $attributes->class('viz') }}
    x-data="{
        tip: null, x: 0, y: 0,
        mostrar(e, titulo, detalle) { this.tip = { titulo, detalle }; this.mover(e); },
        mover(e) { this.x = Math.min(e.clientX + 14, window.innerWidth - 270); this.y = e.clientY + 16; },
        ocultar() { this.tip = null; },
    }"
>
    <ul class="mapa-leyenda mb-3" aria-label="Cómo leer el mapa">
        <li><span class="mapa-celda mapa-coincide" aria-hidden="true">✓</span> Respaldado por el CV</li>
        <li><span class="mapa-celda mapa-revisar" aria-hidden="true">?</span> Conviene revisarlo</li>
        <li><span class="mapa-celda mapa-no" aria-hidden="true">✗</span> No aparece</li>
        <li><span class="mapa-celda mapa-sin" aria-hidden="true">·</span> Sin calcular</li>
    </ul>

    <div class="mapa">
        <table aria-label="{{ $rotulo }}">
            <thead>
                <tr>
                    <th scope="col" class="mapa-fila-titulo text-12 text-neutral-700">Candidato</th>
                    @foreach ($columnas as $columna)
                        <th scope="col" class="mapa-columna" @if ($columna['obligatorio']) data-obligatorio @endif title="{{ $columna['competencia'] }}{{ $columna['obligatorio'] ? ' (obligatorio)' : '' }}">
                            <span>{{ $columna['competencia'] }}{{ $columna['obligatorio'] ? '*' : '' }}</span>
                            @if ($columna['obligatorio'])<span class="sr-only"> (obligatorio)</span>@endif
                        </th>
                    @endforeach
                    <th scope="col" class="mapa-porcentaje text-12 text-neutral-700">Coincidencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr wire:key="mapa-{{ $fila['clave'] }}">
                        <th scope="row" class="mapa-fila-titulo">
                            <a href="{{ $fila['url'] }}" wire:navigate>{{ $fila['titulo'] }}</a>
                            @if ($fila['subtitulo'])<small>{{ $fila['subtitulo'] }}</small>@endif
                        </th>
                        @foreach ($columnas as $j => $columna)
                            @php
                                $nivel = $fila['niveles'][$j] ?? null;
                                $clase = match ($nivel) {
                                    NivelCoincidencia::Coincide => 'mapa-coincide',
                                    NivelCoincidencia::Revisar => 'mapa-revisar',
                                    NivelCoincidencia::NoEncontrado => 'mapa-no',
                                    default => 'mapa-sin',
                                };
                                $simbolo = match ($nivel) {
                                    NivelCoincidencia::Coincide => '✓',
                                    NivelCoincidencia::Revisar => '?',
                                    NivelCoincidencia::NoEncontrado => '✗',
                                    default => '·',
                                };
                                $texto = $nivel?->etiqueta() ?? 'Sin calcular';
                            @endphp
                            <td
                                class="mapa-celda {{ $clase }}"
                                x-on:pointerenter="mostrar($event, @js($fila['titulo'].' · '.$columna['competencia']), @js($texto.($columna['obligatorio'] ? ' · obligatorio' : '')))"
                                x-on:pointermove="mover($event)"
                                x-on:pointerleave="ocultar()"
                            >
                                <span aria-hidden="true">{{ $simbolo }}</span>
                                <span class="sr-only">{{ $columna['competencia'] }}: {{ $texto }}</span>
                            </td>
                        @endforeach
                        <td class="mapa-porcentaje">{{ $fila['porcentaje'] === null ? '—' : $fila['porcentaje'].' %' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="viz-tip" style="display: none" x-show="tip" x-bind:style="`left: ${x}px; top: ${y}px`" aria-hidden="true">
        <strong x-text="tip && tip.titulo"></strong>
        <span x-text="tip && tip.detalle"></span>
    </div>
</div>
