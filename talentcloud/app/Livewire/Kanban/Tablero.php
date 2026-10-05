<?php

namespace App\Livewire\Kanban;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\OpcionesPorEmpresa;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kanban de una vacante (prototipo: isKanban): seis columnas, arrastrar y
 * soltar, "Mover a…" accesible por teclado y "Descartar" con confirmación.
 */
class Tablero extends Component
{
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
            return view('livewire.kanban.tablero', ['vacante' => null])->title('Kanban');
        }

        $postulaciones = Postulacion::query()
            ->with('candidato')
            ->withCount('notas')
            ->where('vacante_id', $vacante->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $columnas = [];
        foreach (EtapaPostulacion::cases() as $etapa) {
            $columnas[] = [
                'etapa' => $etapa,
                'postulaciones' => $postulaciones->filter(fn (Postulacion $p) => $p->etapa === $etapa)->values(),
            ];
        }

        $seleccionada = $this->postulacionSeleccionada ? $postulaciones->firstWhere('id', $this->postulacionSeleccionada) : null;

        $vacantesActivas = Vacante::query()
            ->with('empresa:id,nombre')
            ->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision])
            ->orderBy('titulo')
            ->get(['id', 'titulo', 'empresa_id']);

        return view('livewire.kanban.tablero', [
            'vacante' => $vacante,
            'columnas' => $columnas,
            'activas' => $postulaciones->filter(fn (Postulacion $p) => $p->etapa !== EtapaPostulacion::Descartado)->count(),
            // Mismo criterio que PostulacionPolicy@update: personal de la empresa de la vacante.
            'editable' => $vacante->estado !== EstadoVacante::Cerrada && $this->usuario()->can('update', $vacante),
            'seleccionada' => $seleccionada,
            'etapas' => EtapaPostulacion::opciones(),
            // El selector solo aparece si hay a qué otra vacante cambiar; en la vista global, agrupado por empresa.
            'hayOtrasVacantes' => $vacantesActivas->count() > 1,
            'otrasVacantes' => OpcionesPorEmpresa::de($vacantesActivas, fn (Vacante $v) => $v, fn (Vacante $v) => $v->titulo),
        ])->title('Kanban · '.$vacante->titulo);
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
}
