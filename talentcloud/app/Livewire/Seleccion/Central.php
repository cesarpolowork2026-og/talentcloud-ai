<?php

namespace App\Livewire\Seleccion;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Matching\CumplimientoDeRequisitos;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\OpcionesPorEmpresa;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * «Selección»: el trabajo del reclutador sobre las postulaciones de una vacante, en dos
 * vistas de los mismos datos. El tablero (prototipo: isKanban) tiene seis columnas, arrastrar
 * y soltar, «Mover a…» accesible por teclado y «Descartar» con confirmación; el ranking las
 * ordena por coincidencia con lo que pide la vacante. El porcentaje ordena y explica: nunca
 * mueve ni descarta a nadie.
 */
class Central extends Component
{
    public const VISTAS = ['tablero', 'ranking'];

    #[Locked]
    public ?int $vacanteId = null;

    /** «tablero» (columnas por etapa) o «ranking» (lista ordenada por coincidencia). */
    #[Url(as: 'vista', except: 'tablero')]
    public string $vista = 'tablero';

    /** Solo quienes no tienen requisitos obligatorios sin cubrir según el matching. */
    #[Url(as: 'obligatorios', except: false)]
    public bool $soloObligatorios = false;

    /** Ranking: incluir a quienes ya están descartados. */
    #[Url(as: 'descartados', except: false)]
    public bool $verDescartados = false;

    public bool $mostrarDetalle = false;

    public bool $mostrarDescarte = false;

    #[Locked]
    public ?int $postulacionSeleccionada = null;

    public string $etapaDestino = '';

    public function mount(?Vacante $vacante = null): void
    {
        $this->authorize('viewAny', Vacante::class);

        // /kanban sin vacante: la activa con movimiento más reciente, como el enlace del prototipo.
        $vacante ??= Vacante::query()
            ->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision])
            ->orderByDesc('updated_at')
            ->first();

        if ($vacante !== null) {
            $this->authorize('view', $vacante);
            $this->vacanteId = $vacante->id;
        }
    }

    public function updatedVista(string $valor): void
    {
        if (! in_array($valor, self::VISTAS, true)) {
            $this->vista = 'tablero';
        }
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

        $todas = Postulacion::query()
            ->with('candidato')
            ->withCount('notas')
            ->withMin(['entrevistas as proxima_entrevista' => fn ($q) => $q
                ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
                ->where('inicia_at', '>=', now())], 'inicia_at')
            ->where('vacante_id', $vacante->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        // Cuánto de lo que pide la vacante cubre cada CV, según el matching guardado.
        $requisitos = $vacante->requisitos()->with('competencia')->get();
        $cumplimiento = $todas->mapWithKeys(fn (Postulacion $p) => [$p->id => CumplimientoDeRequisitos::de($p->match_detalle, $requisitos)]);

        $postulaciones = $this->soloObligatorios
            ? $todas->filter(fn (Postulacion $p) => $cumplimiento[$p->id]->cubreLosObligatorios())->values()
            : $todas;

        $columnas = [];
        foreach (EtapaPostulacion::cases() as $etapa) {
            $columnas[] = [
                'etapa' => $etapa,
                'postulaciones' => $postulaciones->filter(fn (Postulacion $p) => $p->etapa === $etapa)->values(),
            ];
        }

        // Ranking: de mayor a menor coincidencia; sin cálculo, al final y por orden de llegada.
        $ranking = $postulaciones
            ->reject(fn (Postulacion $p) => ! $this->verDescartados && $p->etapa === EtapaPostulacion::Descartado)
            ->sort(fn (Postulacion $a, Postulacion $b) => [$b->match_score ?? -1, $a->id] <=> [$a->match_score ?? -1, $b->id])
            ->values();

        $seleccionada = $this->postulacionSeleccionada ? $todas->firstWhere('id', $this->postulacionSeleccionada) : null;

        $vacantesActivas = Vacante::query()
            ->with('empresa:id,nombre')
            ->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision])
            ->orderBy('titulo')
            ->get(['id', 'titulo', 'empresa_id']);

        return view('livewire.seleccion.central', [
            'vacante' => $vacante,
            'columnas' => $columnas,
            'ranking' => $ranking,
            'cumplimiento' => $cumplimiento,
            'ocultas' => $todas->count() - $postulaciones->count(),
            'activas' => $todas->filter(fn (Postulacion $p) => $p->etapa !== EtapaPostulacion::Descartado)->count(),
            // Mismo criterio que PostulacionPolicy@update: personal de la empresa de la vacante.
            'editable' => $vacante->estado !== EstadoVacante::Cerrada && $this->usuario()->can('update', $vacante),
            'seleccionada' => $seleccionada,
            // "Evaluar" solo cuando se puede (entrevista realizada, no retirada, aún sin evaluar por mí).
            'puedeEvaluar' => $seleccionada !== null && $this->puedeEvaluar($seleccionada),
            'etapas' => EtapaPostulacion::opciones(),
            // El selector solo aparece si hay a qué otra vacante cambiar; en la vista global, agrupado por empresa.
            'hayOtrasVacantes' => $vacantesActivas->count() > 1,
            'otrasVacantes' => OpcionesPorEmpresa::de($vacantesActivas, fn (Vacante $v) => $v, fn (Vacante $v) => $v->titulo),
        ])->title('Selección · '.$vacante->titulo);
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
