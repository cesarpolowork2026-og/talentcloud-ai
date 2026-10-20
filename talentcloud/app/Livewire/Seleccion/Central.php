<?php

namespace App\Livewire\Seleccion;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\AnalisisBasicoDeRequisitos;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\BuscadorDeVacantes;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * «Selección»: el trabajo del reclutador sobre las postulaciones de una vacante,
 * en un tablero de seis columnas (arrastrar y soltar, «Mover a...» por teclado y
 * «Descartar» con confirmación). Mover o descartar es siempre decisión de una persona.
 */
class Central extends Component
{
    /** Tarjetas que muestra cada columna al abrir, y las que suma cada «Ver más». Una vacante masiva tiene miles. */
    public const TARJETAS_POR_COLUMNA = 25;

    /**
     * Tope de tarjetas por columna. `$mostrando` es una propiedad pública: un cliente podría enviar un valor
     * enorme para que el servidor cargue y pinte miles de tarjetas.
     */
    public const MAXIMO_POR_COLUMNA = 500;

    /**
     * Columnas del tablero que se desplegaron: etapa => cuántas tarjetas se muestran.
     *
     * @var array<string, int>
     */
    public array $mostrando = [];

    #[Locked]
    public ?int $vacanteId = null;

    public bool $mostrarDetalle = false;

    public bool $mostrarDescarte = false;

    #[Locked]
    public ?int $postulacionSeleccionada = null;

    public string $etapaDestino = '';

    public function mount(?Vacante $vacante = null): void
    {
        $this->authorize('viewAny', Vacante::class);

        // /seleccion sin vacante: la activa con más postulaciones en curso (donde hay algo que revisar) y, a igualdad, la de
        // movimiento más reciente. Solo por «modificada más recientemente» abría una vacante recién creada y vacía.
        $vacante ??= Vacante::query()
            ->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision])
            ->orderByDesc(
                Postulacion::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('postulaciones.vacante_id', 'vacantes.id')
                    ->whereNotIn('etapa', [EtapaPostulacion::Contratado->value, EtapaPostulacion::Descartado->value])
            )
            ->orderByDesc('updated_at')
            ->first();

        if ($vacante !== null) {
            $this->authorize('view', $vacante);
            $this->vacanteId = $vacante->id;
        }
    }

    /** «Ver más» de una columna: suma otro bloque de tarjetas (el servidor decide el tope: lo que existe). */
    public function verMas(string $etapa): void
    {
        $destino = EtapaPostulacion::tryFrom($etapa);
        abort_if($destino === null, 404);

        $this->mostrando[$destino->value] = min(self::MAXIMO_POR_COLUMNA, $this->tarjetasMostradas($destino) + self::TARJETAS_POR_COLUMNA);
    }

    /** Cuántas tarjetas de la columna se pintan: lo pedido por la persona, entre un bloque y el tope. */
    private function tarjetasMostradas(EtapaPostulacion $etapa): int
    {
        $pedidas = (int) ($this->mostrando[$etapa->value] ?? self::TARJETAS_POR_COLUMNA);

        return max(self::TARJETAS_POR_COLUMNA, min(self::MAXIMO_POR_COLUMNA, $pedidas));
    }

    /**
     * Buscador de vacantes (cambiar de vacante): hasta 20 coincidencias, no todas las de la empresa..
     *
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    #[Renderless]
    public function buscarVacantes(string $texto): array
    {
        $this->authorize('viewAny', Vacante::class);

        return app(BuscadorDeVacantes::class)->buscar($texto, soloActivas: true);
    }

    public function mover(int $postulacionId, string $etapa, int $posicion, MoverPostulacion $mover): void
    {
        $postulacion = $this->postulacion($postulacionId);
        $this->authorize('update', $postulacion);

        $destino = EtapaPostulacion::tryFrom($etapa);
        abort_if($destino === null, 404);

        try {
            $mover->ejecutar($postulacion, $destino, $posicion, $this->usuario());
        } catch (OperacionNoPermitida $e) {
            $this->addError('tablero', $e->getMessage());
        }
    }

    public function abrir(int $postulacionId): void
    {
        $postulacion = $this->postulacion($postulacionId);
        $this->authorize('view', $postulacion);

        $this->resetErrorBag();
        $this->postulacionSeleccionada = $postulacion->id;
        $this->etapaDestino = $postulacion->etapa->value;
        $this->mostrarDetalle = true;
    }

    /** "Mover a…" del diálogo: la alternativa accesible al arrastre. */
    public function moverA(MoverPostulacion $mover): void
    {
        $postulacion = $this->postulacion((int) $this->postulacionSeleccionada);
        $this->authorize('update', $postulacion);

        $this->validate(['etapaDestino' => ['required', Rule::enum(EtapaPostulacion::class)]], attributes: ['etapaDestino' => 'columna']);

        $destino = EtapaPostulacion::from($this->etapaDestino);

        if ($destino !== $postulacion->etapa) {
            $alFinal = Postulacion::where('vacante_id', $postulacion->vacante_id)->where('etapa', $destino)->count();

            try {
                $mover->ejecutar($postulacion, $destino, $alFinal, $this->usuario());
            } catch (OperacionNoPermitida $e) {
                $this->addError('etapaDestino', $e->getMessage());

                return;
            }
        }

        $this->mostrarDetalle = false;
    }

    public function confirmarDescarte(int $postulacionId): void
    {
        $postulacion = $this->postulacion($postulacionId);
        $this->authorize('update', $postulacion);

        $this->postulacionSeleccionada = $postulacion->id;
        $this->mostrarDescarte = true;
    }

    public function descartar(MoverPostulacion $mover): void
    {
        $postulacion = $this->postulacion((int) $this->postulacionSeleccionada);
        $this->authorize('update', $postulacion);

        try {
            $mover->descartar($postulacion, $this->usuario());
        } catch (OperacionNoPermitida $e) {
            $this->addError('tablero', $e->getMessage());
        }

        $this->mostrarDescarte = false;
    }

    public function render(): View
    {
        $vacante = $this->vacanteId ? Vacante::with('empresa')->find($this->vacanteId) : null;

        if ($vacante === null) {
            return view('livewire.seleccion.central', ['vacante' => null])->title('Selección');
        }

        // Cada consulta pide solo lo que se ve: las primeras tarjetas de cada columna. Con miles de
        // postulaciones nada recorre ni hidrata la vacante entera.
        $totales = Postulacion::query()
            ->where('vacante_id', $vacante->id)
            ->toBase()
            ->selectRaw('etapa, COUNT(*) AS total')
            ->groupBy('etapa')
            ->get()
            ->keyBy('etapa');

        $enEtapa = fn (EtapaPostulacion $e): int => (int) ($totales[$e->value]->total ?? 0);
        $todas = (int) $totales->sum('total');

        // Las tarjetas de todas las columnas en dos consultas: los ids de las primeras N de cada etapa (una
        // función de ventana) y esas postulaciones con sus relaciones.
        $porEtapa = $this->tarjetasPorEtapa($vacante->id);

        $columnas = [];
        foreach (EtapaPostulacion::cases() as $etapa) {
            $mostradas = $this->tarjetasMostradas($etapa);
            $total = $enEtapa($etapa);

            $columnas[] = [
                'etapa' => $etapa,
                'total' => $total,
                'restantes' => max(0, $total - $mostradas),
                'enElTope' => $mostradas >= self::MAXIMO_POR_COLUMNA,
                'postulaciones' => collect($porEtapa[$etapa->value] ?? []),
            ];
        }

        // Análisis básico de las tarjetas que se ven (menos las descartadas): dos consultas para todas.
        $coberturas = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($vacante, collect($columnas)
            ->reject(fn (array $c) => $c['etapa'] === EtapaPostulacion::Descartado)
            ->flatMap(fn (array $c) => $c['postulaciones']->pluck('candidato_id'))
            ->all());

        // La postulación abierta se lee completa solo cuando hay una abierta.
        $seleccionada = $this->postulacionSeleccionada
            ? Postulacion::with('candidato')->withCount('notas')->where('vacante_id', $vacante->id)->find($this->postulacionSeleccionada)
            : null;

        return view('livewire.seleccion.central', [
            'vacante' => $vacante,
            'columnas' => $columnas,
            'coberturas' => $coberturas,
            'activas' => $todas - (int) ($totales[EtapaPostulacion::Descartado->value]->total ?? 0),
            // Mismo criterio que PostulacionPolicy@update: personal de la empresa de la vacante.
            'editable' => $vacante->estado !== EstadoVacante::Cerrada && $this->usuario()->can('update', $vacante),
            'vacanteCerrada' => $vacante->estado === EstadoVacante::Cerrada,
            'seleccionada' => $seleccionada,
            // "Evaluar" solo cuando se puede (entrevista realizada, no retirada, aún sin evaluar por mí).
            'puedeEvaluar' => $seleccionada !== null && $this->puedeEvaluar($seleccionada),
            'etapas' => EtapaPostulacion::opciones(),
            // El buscador de vacantes solo aparece si hay a qué otra vacante cambiar.
            'hayOtrasVacantes' => Vacante::query()->whereIn('estado', EstadoVacante::activos())->whereKeyNot($vacante->id)->exists(),
        ])->title('Selección · '.$vacante->titulo);
    }

    /**
     * Las primeras tarjetas de cada columna (`$mostrando`), agrupadas por etapa.
     *
     * @return array<string, list<Postulacion>>
     */
    private function tarjetasPorEtapa(int $vacanteId): array
    {
        $limites = [];
        foreach (EtapaPostulacion::cases() as $etapa) {
            $limites[$etapa->value] = $this->tarjetasMostradas($etapa);
        }

        $numeradas = Postulacion::query()->where('vacante_id', $vacanteId)
            ->toBase()
            ->selectRaw('id, etapa, ROW_NUMBER() OVER (PARTITION BY etapa ORDER BY orden, id) AS fila');

        $casos = implode(' ', array_fill(0, count($limites), 'WHEN ? THEN ?'));
        $ids = DB::query()
            ->fromSub($numeradas, 'numeradas')
            ->whereRaw("fila <= CASE etapa {$casos} ELSE 0 END", collect($limites)->flatMap(fn (int $n, string $e) => [$e, $n])->all())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->tarjetas($vacanteId)
            ->whereIn('id', $ids)
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Postulacion $p) => $p->etapa->value)
            ->map(fn ($grupo) => $grupo->values()->all())
            ->all();
    }

    /**
     * Las postulaciones de la vacante con solo las columnas que pinta una tarjeta.
     *
     * @return Builder<Postulacion>
     */
    private function tarjetas(int $vacanteId): Builder
    {
        return Postulacion::query()
            ->select(['id', 'empresa_id', 'vacante_id', 'candidato_id', 'etapa', 'orden', 'etapa_cambiada_at', 'created_at', 'retirada_at'])
            ->with('candidato:id,nombre,titular')
            ->withCount('notas')
            ->withMin(['entrevistas as proxima_entrevista' => fn ($q) => $q
                ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
                ->where('inicia_at', '>=', now())], 'inicia_at')
            ->where('vacante_id', $vacanteId);
    }

    /** Solo postulaciones de la vacante del tablero (y, por el filtro, de la propia empresa). */
    private function postulacion(int $id): Postulacion
    {
        $postulacion = Postulacion::with(['candidato', 'vacante'])
            ->where('vacante_id', $this->vacanteId)
            ->find($id);

        abort_if($postulacion === null, 404);

        return $postulacion;
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function puedeEvaluar(Postulacion $postulacion): bool
    {
        if (! $this->usuario()->can('create', Evaluacion::class)) {
            return false;
        }

        try {
            app(GuardarEvaluacion::class)->verificarEvaluable($postulacion, $this->usuario());

            return true;
        } catch (OperacionNoPermitida) {
            return false;
        }
    }
}
