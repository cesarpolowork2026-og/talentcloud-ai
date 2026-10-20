{{--
    «Cómo funciona» de la presentación: los tres pasos del proceso, de la vacante a la decisión.
--}}
<section id="como-funciona" class="como-funciona py-16" aria-labelledby="como-funciona-titulo">
    <div class="mx-auto max-w-[1480px] px-4 sm:px-8">
        <x-presentacion.encabezado rotulo="Cómo funciona" titulo="De la vacante a la decisión, en tres pasos" id="como-funciona-titulo">
            Publica la vacante, recibe y organiza a los candidatos en un tablero, y decide con el historial, las entrevistas y las evaluaciones a la vista.
        </x-presentacion.encabezado>

        <ol class="m-0 grid list-none gap-8 p-0 md:grid-cols-3">
            @foreach ([
                ['briefcase', 'Publica tu vacante', 'Define requisitos obligatorios y deseables, con los años mínimos de experiencia, y ábrela a postulantes.'],
                ['file-text', 'Recibe y organiza candidatos', 'Cada CV queda guardado de forma privada y cada postulación avanza por las etapas del tablero, con sus notas.'],
                ['scales', 'Tú decides', 'Entrevista, evalúa por criterios y avanza, descarta o contrata: lo decide siempre una persona de tu equipo.'],
            ] as $indice => [$icono, $titulo, $texto])
                <li class="flex items-start gap-4">
                    <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-full bg-accent-100 text-accent-700">
                        <x-ui.icon :name="$icono" :size="22" />
                        <span class="absolute -right-1 -top-1 grid h-6 w-6 place-items-center rounded-full bg-accent-700 text-12 font-semibold text-white" aria-hidden="true">{{ $indice + 1 }}</span>
                    </span>
                    <div>
                        <h3 class="mb-1 text-17">{{ $titulo }}</h3>
                        <p class="m-0 text-14 text-neutral-700">{{ $texto }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
