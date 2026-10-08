<?php

namespace App\Livewire\Evaluaciones;

use App\DTO\EvaluacionData;
use App\Enums\CriterioEvaluacion;
use App\Enums\EstadoEvaluacion;
use App\Exports\EvaluacionesExport;
use App\Livewire\Concerns\WithListado;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EvaluacionRepository;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pantalla "Evaluaciones" (prototipo: isEvaluaciones) + formulario por criterios. */
#[Title('Evaluaciones')]
class Listado extends Component
{
    use WithListado;

    // Detalle (diálogo del prototipo).
    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $evaluacionSeleccionada = null;

    // Formulario: nueva evaluación o edición de una existente.
    public bool $mostrarFormulario = false;

    #[Locked]
    public ?int $editando = null;

    public string $postulacionId = '';

    /** @var array<string, string> valor de CriterioEvaluacion => puntaje escrito */
    public array $puntajes = [];

    public string $comentario = '';

    // "estado" ya es el filtro del listado (WithListado).
    public string $estadoEvaluacion = 'pendiente';

    public function mount(): void
    {
        $this->authorize('viewAny', Evaluacion::class);

        // Acceso directo desde el Kanban: "Evaluar".
        if (request()->filled('evaluar')) {
            $this->abrirFormulario(request()->integer('evaluar'));
        }
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['postulacion.candidato.nombre', 'postulacion.vacante.titulo'];
    }

    public function verDetalle(int $id): void
    {
        $evaluacion = $this->evaluacion($id);
        $this->authorize('view', $evaluacion);

        $this->evaluacionSeleccionada = $evaluacion->id;
        $this->mostrarDetalle = true;
    }

    public function abrirFormulario(?int $postulacionId = null): void
    {
        $this->authorize('create', Evaluacion::class);

        $this->prepararFormulario();
        $this->postulacionId = $postulacionId ? (string) $postulacionId : '';
        $this->mostrarFormulario = true;

        // Acceso directo desde el Kanban: si aún no se puede evaluar, se dice por qué.
        $postulacion = $postulacionId ? Postulacion::with(['candidato', 'vacante'])->find($postulacionId) : null;

        if ($postulacion !== null) {
            try {
                app(GuardarEvaluacion::class)->verificarEvaluable($postulacion, $this->usuario());
            } catch (OperacionNoPermitida $e) {
                $this->postulacionId = '';
                $this->addError('postulacionId', $e->getMessage());
            }
        }
    }

    public function editar(int $id): void
    {
        $evaluacion = $this->evaluacion($id);
        $this->authorize('update', $evaluacion);

        $this->prepararFormulario();
        $this->editando = $evaluacion->id;
        $this->comentario = (string) $evaluacion->comentario;
        $this->estadoEvaluacion = $evaluacion->estado->value;
        foreach (CriterioEvaluacion::cases() as $criterio) {
            $guardado = collect($evaluacion->criterios)->firstWhere('nombre', $criterio->etiqueta());
            $this->puntajes[$criterio->value] = isset($guardado['puntaje']) ? (string) $guardado['puntaje'] : '';
        }

        $this->mostrarDetalle = false;
        $this->mostrarFormulario = true;
    }

    public function guardar(GuardarEvaluacion $guardar): void
    {
        $datos = $this->validate([
            'postulacionId' => [$this->editando ? 'nullable' : 'required', 'integer'],
            'puntajes' => ['required', 'array'],
            'puntajes.*' => ['required', 'numeric', 'between:0,'.CriterioEvaluacion::PUNTAJE_MAXIMO],
            'comentario' => ['nullable', 'string', 'max:2000'],
            'estadoEvaluacion' => ['required', Rule::enum(EstadoEvaluacion::class)],
        ], attributes: [
            'postulacionId' => 'candidato',
            'puntajes.*' => 'puntaje',
            'estadoEvaluacion' => 'estado',
        ]);

        $evaluacionData = new EvaluacionData(
            puntajes: array_map('floatval', $datos['puntajes']),
            comentario: $datos['comentario'] ?: null,
            estado: EstadoEvaluacion::from($datos['estadoEvaluacion']),
        );

        try {
            if ($this->editando !== null) {
                $evaluacion = $this->evaluacion($this->editando);
                $this->authorize('update', $evaluacion);
                $guardar->actualizar($evaluacion, $evaluacionData);
            } else {
                $this->authorize('create', Evaluacion::class);
                $guardar->registrar((int) $datos['postulacionId'], $this->usuario(), $evaluacionData);
            }
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'postulacionId', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
    }

    /** Promedio en vivo de los criterios ya puntuados (el definitivo lo calcula el servicio). */
    #[Computed]
    public function promedio(): ?float
    {
        $validos = array_filter($this->puntajes, fn (string $p) => is_numeric($p) && $p >= 0 && $p <= CriterioEvaluacion::PUNTAJE_MAXIMO);

        return Evaluacion::promediar(array_map(fn (string $p) => ['nombre' => '', 'puntaje' => (float) $p], array_values($validos)));
    }

    /** "Exportar a Excel": lo que se ve, con la búsqueda y los filtros vigentes. */
    public function exportar(EvaluacionRepository $evaluaciones): BinaryFileResponse
    {
        $this->authorize('viewAny', Evaluacion::class);

        return Excel::download(new EvaluacionesExport($this->filtrar($evaluaciones->consultaListado()->latest())), 'evaluaciones-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(EvaluacionRepository $evaluaciones): View
    {
        $seleccionada = $this->evaluacionSeleccionada ? $evaluaciones->buscar($this->evaluacionSeleccionada) : null;
        $porEvaluar = $this->mostrarFormulario && $this->editando === null
            ? $evaluaciones->postulacionesPorEvaluar($this->usuario())
            : collect();

        return view('livewire.evaluaciones.listado', [
            'filas' => $this->listar($evaluaciones->consultaListado()->latest()),
            // Admin SaaS: columna "Empresa" (ve las evaluaciones de todas).
            'vistaGlobal' => app(TenantContext::class)->esGlobal(),
            'seleccionada' => $seleccionada,
            'postulaciones' => $porEvaluar->mapWithKeys(fn (Postulacion $p) => [$p->id => $p->candidato?->nombre.' — '.$p->vacante?->titulo])->all(),
            'enEdicion' => $this->editando ? $evaluaciones->buscar($this->editando) : null,
            'criterios' => CriterioEvaluacion::cases(),
            'estados' => EstadoEvaluacion::opciones(),
        ]);
    }

    private function prepararFormulario(): void
    {
        $this->resetValidation();
        $this->reset('editando', 'postulacionId', 'comentario', 'estadoEvaluacion');
        $this->puntajes = array_fill_keys(array_map(fn (CriterioEvaluacion $c) => $c->value, CriterioEvaluacion::cases()), '');
    }

    private function evaluacion(int $id): Evaluacion
    {
        $evaluacion = app(EvaluacionRepository::class)->buscar($id);

        abort_if($evaluacion === null, 404);

        return $evaluacion;
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
