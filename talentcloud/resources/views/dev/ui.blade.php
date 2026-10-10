<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Catálogo UI'])
</head>
<body>
<main class="mx-auto max-w-page p-8">
    <x-ui.page-header
        title="Catálogo de componentes"
        subtitle="Componentes x-ui.* construidos con el design system Broadsheet. Compararlos con TalentCloud AI Platform/."
    />

    {{-- Botones y etiquetas --}}
    <section class="mb-8">
        <h3 class="mb-3 text-16">Botones</h3>
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button variant="primary">Iniciar sesión</x-ui.button>
            <x-ui.button>Cancelar</x-ui.button>
            <x-ui.button variant="ghost">Ver evidencia</x-ui.button>
            <x-ui.button variant="danger">Descartar</x-ui.button>
            <x-ui.button variant="ghost" size="sm">Reintentar análisis</x-ui.button>
            <x-ui.button variant="ghost" icon aria-label="Cambiar dirección">↑</x-ui.button>
            <x-ui.button variant="primary" disabled>Deshabilitado</x-ui.button>
            <x-ui.button variant="secondary" href="#">Como enlace</x-ui.button>
        </div>
        <h3 class="mb-3 mt-6 text-16">Etiquetas</h3>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.tag tone="accent">Abierta</x-ui.tag>
            <x-ui.tag tone="neutral">En revisión</x-ui.tag>
            <x-ui.tag tone="outline">Cerrada</x-ui.tag>
            <x-ui.tag tone="accent-2">No encontrado</x-ui.tag>
            <x-ui.tag tone="accent">88% match</x-ui.tag>
        </div>
    </section>

    {{-- Formularios --}}
    <section class="mb-8 max-w-[400px]">
        <h3 class="mb-3 text-16">Formularios</h3>
        <div class="grid gap-4">
            <x-ui.field label="Correo electrónico" for="demo-email">
                <x-ui.input id="demo-email" type="email" placeholder="reclutador@empresa.com" />
            </x-ui.field>
            <x-ui.field label="Contraseña" for="demo-pass" hint="Mínimo 8 caracteres.">
                <x-ui.input id="demo-pass" type="password" placeholder="••••••••••••" />
            </x-ui.field>
            <x-ui.field label="Estado">
                <x-ui.select :options="['abierta' => 'Abierta', 'borrador' => 'Borrador']" class="w-full" />
            </x-ui.field>
            <x-ui.field label="Descripción">
                <x-ui.textarea placeholder="Describe el puesto…" />
            </x-ui.field>
            <div class="field">
                <label id="demo-role">Ingresar como</label>
                <x-ui.seg labelledby="demo-role">
                    <x-ui.seg-option name="role">Admin SaaS</x-ui.seg-option>
                    <x-ui.seg-option name="role">Empresa</x-ui.seg-option>
                    <x-ui.seg-option name="role" checked>Reclutador</x-ui.seg-option>
                    <x-ui.seg-option name="role">Postulante</x-ui.seg-option>
                </x-ui.seg>
            </div>
            <div class="flex gap-4">
                <x-ui.radio name="tipo" checked>Obligatorio</x-ui.radio>
                <x-ui.radio name="tipo">Deseable</x-ui.radio>
            </div>
            <x-ui.checkbox checked>Recordarme</x-ui.checkbox>
        </div>
    </section>

    {{-- Tarjetas e indicadores --}}
    <section class="mb-8">
        <h3 class="mb-3 text-16">Indicadores</h3>
        <div class="mb-6 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <x-ui.stat label="Vacantes activas" value="12" />
            <x-ui.stat label="Candidatos evaluados" value="184" />
            <x-ui.stat label="Match promedio" value="78%" highlight x-on:click="$dispatch('open-dialog', 'demo')" />
            <x-ui.stat label="Entrevistas hoy" value="5" />
        </div>

        <h3 class="mb-3 text-16">Tarjetas</h3>
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <x-ui.card kicker="Desarrolladora Backend" title="Elena Ruiz" class="cursor-pointer">
                <p class="card-body">Postuló a Desarrollador Backend</p>
                <div class="card-meta">
                    <x-ui.tag tone="accent">88% match</x-ui.tag>
                    <x-ui.tag tone="outline" class="ml-1">Java</x-ui.tag>
                    <x-ui.tag tone="outline" class="ml-1">Spring Boot</x-ui.tag>
                </div>
            </x-ui.card>
            <x-ui.card kicker="QA Engineer" title="Pablo Vega">
                <p class="card-body">Postuló a QA Engineer</p>
                <div class="card-meta text-accent-2-700">
                    <x-ui.icon name="warning" :size="14" />
                    <span>Error al analizar el CV</span>
                </div>
                <x-ui.button variant="ghost" size="sm" class="mt-2 self-start">Reintentar análisis</x-ui.button>
            </x-ui.card>
            <x-ui.card kicker="Mi currículo" title="CV_MarcoDiaz.pdf" elev="sm">
                <p class="card-body">Analizado por la IA — 6 competencias detectadas.</p>
                <x-ui.button block class="mt-3">Subir nuevo CV</x-ui.button>
            </x-ui.card>
        </div>
    </section>

    {{-- Matching --}}
    <section class="mb-8 max-w-[760px]">
        <h3 class="mb-3 text-16">Requisito de matching</h3>
        <x-ui.card class="p-4" x-data="{ abierto: false }">
            <div class="flex flex-wrap items-center gap-3">
                <span class="w-[180px] text-15 font-semibold">Java</span>
                <x-ui.progress :value="96" label="Coincidencia con Java" class="min-w-[120px] flex-1" />
                <x-ui.tag tone="accent">Coincide</x-ui.tag>
                <x-ui.button variant="ghost" size="sm" x-on:click="abierto = ! abierto" x-text="abierto ? 'Ocultar evidencia' : 'Ver evidencia'">Ver evidencia</x-ui.button>
            </div>
            <p x-show="abierto" x-cloak class="m-0 mt-3 border-t border-divider pt-3 text-14 italic text-neutral-800">"5 años de experiencia con Java en proyectos backend de producción."</p>
        </x-ui.card>
    </section>

    {{-- Tabla --}}
    <section class="mb-8">
        <x-ui.breadcrumb :items="[['label' => 'Vacantes', 'href' => '#'], ['label' => 'Desarrollador Backend']]" />
        <h3 class="mb-3 text-16">Vacantes recientes</h3>
        <x-ui.table>
            <x-slot:head>
                <th>Vacante</th><th>Empresa</th><th>Candidatos</th><th>Estado</th>
            </x-slot:head>
            <tr class="tc-row"><td>Desarrollador Backend</td><td>Nimbus Corp</td><td>18</td><td><x-ui.tag tone="accent">Abierta</x-ui.tag></td></tr>
            <tr class="tc-row"><td>Data Analyst</td><td>Nimbus Corp</td><td>24</td><td><x-ui.tag>En revisión</x-ui.tag></td></tr>
            <tr class="tc-row"><td>DevOps Engineer</td><td>Vela Studio</td><td>15</td><td><x-ui.tag tone="outline">Cerrada</x-ui.tag></td></tr>
        </x-ui.table>
        <x-ui.empty-state icon="stack" message="No se encontraron vacantes con estos filtros." />
    </section>

    {{-- Diálogos --}}
    <section class="mb-8">
        <h3 class="mb-3 text-16">Diálogos</h3>
        <div class="flex gap-3">
            <x-ui.button x-data x-on:click="$dispatch('open-dialog', 'demo')">Abrir diálogo</x-ui.button>
            <x-ui.button variant="danger" x-data x-on:click="$dispatch('open-dialog', 'confirmar')">Abrir confirmación</x-ui.button>
        </div>

        <x-ui.dialog name="demo" title="Desarrollador Backend" max-width="480px">
            <p class="whitespace-pre-line">Nimbus Corp · Remoto
18 candidatos · Estado: Abierta
Publicada el 2 sep.</p>
            <x-slot:actions>
                <x-ui.button x-on:click="open = false">Cerrar</x-ui.button>
            </x-slot:actions>
        </x-ui.dialog>

        <x-ui.confirm name="confirmar" title="Descartar a Pablo Vega" action-label="Descartar" action="$refresh">
            El candidato se moverá fuera del proceso de esta vacante. Esta acción no elimina su perfil ni su CV.
        </x-ui.confirm>
    </section>

    {{-- Carga --}}
    <section class="mb-8">
        <h3 class="mb-3 text-16">Carga</h3>
        <x-ui.page-skeleton />
    </section>

    {{-- Iconos --}}
    <section>
        <h3 class="mb-3 text-16">Iconos (Phosphor duotone)</h3>
        <div class="flex flex-wrap gap-4 text-neutral-700">
            @foreach (['bell', 'lock', 'check-circle', 'sparkle', 'stack', 'warning', 'question', 'briefcase', 'users', 'kanban', 'calendar', 'clipboard-text', 'file-text', 'upload-simple', 'buildings', 'magnifying-glass'] as $icono)
                <span class="flex flex-col items-center gap-1 text-11"><x-ui.icon :name="$icono" :size="20" />{{ $icono }}</span>
            @endforeach
        </div>
    </section>

    {{-- Ilustraciones propias (x-ilustracion): todas juntas, para revisar que comparten estilo. --}}
    <section class="mt-8" id="ilustraciones">
        <h2 class="mb-4 text-20">Ilustraciones</h2>
        <div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
            @foreach (['primera-vacante', 'sin-candidatos', 'agenda-libre', 'sin-postulaciones', 'pagina-no-encontrada', 'acceso-restringido', 'hoja-enviada'] as $nombre)
                <figure class="m-0 grid gap-2 rounded border border-divider bg-white p-4">
                    <x-ilustracion :nombre="$nombre" class="mx-auto w-[200px]" />
                    <figcaption class="text-center text-13 text-neutral-700">{{ $nombre }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>
</main>
@livewireScripts
</body>
</html>
