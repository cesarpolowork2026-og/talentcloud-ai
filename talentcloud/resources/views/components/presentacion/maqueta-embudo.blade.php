{{-- Maqueta: embudo de selección con la conversión entre etapas y el tiempo en cada etapa (datos ficticios fijos). --}}
<x-presentacion.marco activo="Dashboard">
    <p class="mb-1 font-heading text-22 font-semibold">Embudo de selección</p>
    <p class="mb-3 text-12 text-neutral-700">Postulaciones de los últimos 30 días y la etapa más avanzada a la que llegó cada una.</p>

    <div class="grid grid-cols-[1.7fr_1fr] gap-4">
        <div class="card elev-md !bg-white">
            <x-grafico.embudo :etapas="[
                ['clave' => 'postulado', 'etiqueta' => 'Postulado', 'total' => 240, 'porcentaje' => 100, 'conversion' => null],
                ['clave' => 'en_revision', 'etiqueta' => 'En revisión', 'total' => 118, 'porcentaje' => 49, 'conversion' => 49],
                ['clave' => 'entrevista', 'etiqueta' => 'Entrevista', 'total' => 52, 'porcentaje' => 22, 'conversion' => 44],
                ['clave' => 'oferta', 'etiqueta' => 'Oferta', 'total' => 14, 'porcentaje' => 6, 'conversion' => 27],
                ['clave' => 'contratado', 'etiqueta' => 'Contratado', 'total' => 6, 'porcentaje' => 3, 'conversion' => 43],
            ]" />
        </div>
        <div class="grid content-start gap-3">
            <x-ui.stat label="Tiempo medio hasta contratar" value="9,4 días" />
            <div class="card elev-md !bg-white">
                <div class="card-kicker">Tiempo en cada etapa</div>
                <x-grafico.barras rotulo="Días medios en cada etapa" :items="[
                    ['clave' => 'postulado', 'etiqueta' => 'Postulado', 'valor' => 2.4, 'texto' => '2,4 días'],
                    ['clave' => 'en_revision', 'etiqueta' => 'En revisión', 'valor' => 9.5, 'texto' => '9,5 días'],
                    ['clave' => 'entrevista', 'etiqueta' => 'Entrevista', 'valor' => 6.2, 'texto' => '6,2 días'],
                    ['clave' => 'oferta', 'etiqueta' => 'Oferta', 'valor' => 2, 'texto' => '2 días'],
                ]" />
            </div>
        </div>
    </div>
</x-presentacion.marco>
