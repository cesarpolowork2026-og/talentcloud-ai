{{--
    «Para postulantes» de la presentación: el otro lado del producto, en el teléfono, que es donde
    se busca empleo. Cada punto corresponde a algo que la aplicación hace de verdad.
--}}
<section id="postulantes" class="py-16" aria-labelledby="postulantes-titulo">
    <div class="mx-auto grid max-w-[1480px] grid-cols-1 items-center gap-12 px-4 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
        <div>
            <p class="mb-2 text-12 uppercase tracking-[0.12em] text-accent-700">Para postulantes</p>
            <h2 id="postulantes-titulo" class="mb-4 max-w-[24ch] text-[clamp(26px,3vw,34px)] leading-[1.15]">Tu búsqueda de empleo, en orden y en tu celular</h2>
            <p class="mb-6 max-w-[52ch] text-16 text-neutral-800">Crear tu cuenta es gratis y siempre lo será. Sin correos perdidos ni procesos de los que nunca supiste nada.</p>
            <ul class="m-0 mb-8 grid list-none gap-3 p-0">
                @foreach ([
                    'Sube tu CV una sola vez, en PDF o Word, y postula en un clic.',
                    'Mira en qué etapa está cada postulación.',
                    'Recibe avisos de tus entrevistas y de los cambios en tu proceso.',
                    'Retira una postulación cuando quieras.',
                    'Descarga o elimina tus datos desde tu perfil.',
                ] as $punto)
                    <li class="flex items-start gap-3 text-15 text-neutral-800">
                        <x-ui.icon name="check-circle" :size="20" class="mt-0.5 shrink-0 text-accent-700" />
                        <span>{{ $punto }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3">
                <x-ui.button variant="primary" :href="route('register.postulante')">Registrarme como postulante</x-ui.button>
                <x-ui.button :href="route('empleos.index')">Ver empleos</x-ui.button>
            </div>
        </div>

        <x-presentacion.telefono>
            <x-presentacion.maqueta-mis-postulaciones-movil />
        </x-presentacion.telefono>
    </div>
</section>
