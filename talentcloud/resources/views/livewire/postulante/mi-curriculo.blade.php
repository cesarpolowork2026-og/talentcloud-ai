{{-- "Mi currículo" del prototipo (card elev-sm en la vista del postulante). --}}
@use('App\Enums\EstadoProcesamientoCv')
@php($procesando = in_array($cv?->estado_procesamiento, [EstadoProcesamientoCv::Pendiente, EstadoProcesamientoCv::Procesando], true))
<div @if ($procesando) wire:poll.4s="esperarCv" @endif @class(['contents' => $soloSubida])>
    @if ($soloSubida)
        <x-ui.button variant="primary" block wire:click="$set('mostrarSubida', true)">{{ $cv ? 'Subir otro CV' : 'Subir mi CV' }}</x-ui.button>
    @else
    <x-ui.card kicker="Mi currículo" elev="sm">
        <div class="card-title mb-2">{{ $cv?->nombre_original ?? 'Aún no subiste tu CV' }}</div>

        @if (! $cv)
            <p class="card-body">Sube tu CV en PDF o Word para postular y que la IA identifique tus competencias.</p>
        @elseif ($cv->estado_procesamiento === EstadoProcesamientoCv::Error)
            <div class="card-meta text-accent-2-700">
                <x-ui.icon name="warning" :size="14" />
                <span>Error al analizar el CV</span>
            </div>
            <p class="card-body">{{ $cv->error }}</p>
            <x-ui.button variant="ghost" size="sm" class="self-start" wire:click="reintentar">Reintentar análisis</x-ui.button>
        @elseif ($procesando)
            <p class="card-body">Analizando tu CV…</p>
        @else
            <p class="card-body">
                Analizado por la IA — {{ $competenciasIa }} {{ $competenciasIa === 1 ? 'competencia detectada' : 'competencias detectadas' }}.
            </p>
        @endif

        @if ($cv)
            {{-- El propio postulante puede ver el archivo que envía a las empresas (CvPolicy::view). --}}
            <a href="{{ route('cvs.descargar', $cv) }}" class="self-start text-13">Descargar mi CV</a>
        @endif

        <x-ui.button block class="mt-3" wire:click="$set('mostrarSubida', true)">{{ $cv ? 'Subir nuevo CV' : 'Subir CV' }}</x-ui.button>
    </x-ui.card>
    @endif

    <x-ui.dialog wire:model="mostrarSubida" :title="$cv ? 'Subir nuevo CV' : 'Subir tu CV'" max-width="480px">
        <form wire:submit="subir" id="form-mi-cv" class="grid gap-3">
            <p class="m-0">La IA extraerá automáticamente tus competencias y experiencia del nuevo archivo.</p>
            <x-ui.field label="Archivo (PDF o Word, hasta 5 MB)" for="mi-cv" error="archivoCv">
                <input type="file" wire:model="archivoCv" id="mi-cv" accept=".pdf,.docx" class="input">
                <p class="field-hint" wire:loading wire:target="archivoCv" role="status">Subiendo archivo…</p>
            </x-ui.field>
        </form>
        <x-slot:actions>
            <x-ui.button x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-mi-cv" variant="primary" wire:loading.attr="disabled" wire:target="subir,archivoCv">Subir</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
