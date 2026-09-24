{{-- Maqueta: candidatos con las competencias que la IA extrajo de su CV. --}}
<x-presentacion.marco activo="Candidatos">
    <p class="mb-1 font-heading text-22 font-semibold">Candidatos</p>
    <p class="mb-4 text-12 text-neutral-700">CVs recibidos y analizados por la IA.</p>

    <div class="grid grid-cols-[1fr_1fr_230px] gap-3">
        @foreach ([
            ['Desarrolladora Backend', 'Elena Ruiz', 'Desarrollador Backend', 78, ['Java', 'Spring Boot', 'PostgreSQL']],
            ['Analista de Datos', 'Lucía Prat', 'Data Analyst', 96, ['SQL', 'Python', 'Power BI']],
            ['Desarrolladora Backend', 'Sofía Nieto', 'Desarrollador Backend', 93, ['Java', 'Docker', 'Kubernetes']],
            ['Diseñador de Producto', 'Iván Torres', 'Desarrollador Backend', 41, ['Figma', 'Research']],
        ] as [$titular, $nombre, $vacante, $match, $competencias])
            <div @class(['card', 'row-start-2' => $loop->index >= 2])>
                <div class="card-kicker">{{ $titular }}</div>
                <p class="m-0 font-heading text-15 font-semibold">{{ $nombre }}</p>
                <p class="m-0 text-11 text-neutral-700">Postuló a {{ $vacante }}</p>
                <div class="flex flex-wrap gap-1">
                    <x-ui.tag tone="accent" class="!text-10">{{ $match }}% match</x-ui.tag>
                    @foreach ($competencias as $competencia)
                        <x-ui.tag tone="outline" class="!text-10">{{ $competencia }}</x-ui.tag>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="card elev-md col-start-3 row-span-2 row-start-1 bg-white">
            <div class="card-kicker">Currículo</div>
            <p class="m-0 font-heading text-14 font-semibold">CV_ElenaRuiz.docx</p>
            <div><x-ui.tag tone="accent" class="!text-10">Procesado</x-ui.tag></div>
            <p class="m-0 text-11 leading-[1.5] text-neutral-800">La IA detectó 8 competencias, 5 años de experiencia con Java y la evidencia de cada una.</p>
            <div class="flex flex-wrap gap-1">
                @foreach (['Java · IA', 'Docker · IA', 'Scrum · IA', 'Inglés · IA'] as $detectada)
                    <x-ui.tag tone="accent" class="!text-10">{{ $detectada }}</x-ui.tag>
                @endforeach
            </div>
        </div>
    </div>
</x-presentacion.marco>
