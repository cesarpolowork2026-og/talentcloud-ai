<?php

namespace App\Livewire\Reclamaciones;

use App\Enums\EstadoReclamacion;
use App\Livewire\Concerns\WithListado;
use App\Models\Reclamacion;
use App\Models\User;
use App\Repositories\Contracts\ReclamacionRepository;
use App\Services\Reclamaciones\PlazoDeRespuesta;
use App\Services\Reclamaciones\ResponderReclamacion;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Reclamaciones" del admin SaaS: las hojas del Libro de Reclamaciones, las más
 * urgentes primero, con su plazo; ver una hoja y registrar su respuesta, que se
 * envía al consumidor.
 */
#[Title('Reclamaciones')]
class Listado extends Component
{
    use WithListado {
        filtrar as filtrarListado;
    }

    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $seleccionada = null;

    public string $respuesta = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Reclamacion::class);

        // Enlace del correo a soporte: /reclamaciones?ver={id}
        $ver = request()->query('ver');
        if (is_numeric($ver)) {
            $this->verDetalle((int) $ver);
        }
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['nombre', 'email', 'numero_documento', 'servicio'];
    }

    /**
     * Con un código de hoja ("LR-2026-000001") se busca por su número; si no, el texto
     * se busca en las columnas de camposBusqueda().
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    protected function filtrar(Builder $consulta): Builder
    {
        if (! preg_match('/^\s*LR-\d{4}-0*(\d{1,9})\s*$/i', $this->busqueda, $codigo)) {
            return $this->filtrarListado($consulta);
        }

        $busqueda = $this->busqueda;
        $this->busqueda = '';

        try {
            return $this->filtrarListado($consulta->where('numero', (int) $codigo[1]));
        } finally {
            $this->busqueda = $busqueda;
        }
    }

    /** @return array<string, string> */
    protected function opcionesEstado(): array
    {
        return EstadoReclamacion::opciones();
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return ['fecha_limite' => 'Fecha límite', 'created_at' => 'Fecha de presentación', 'numero' => 'Número de hoja'];
    }

    public function verDetalle(int $id): void
    {
        $reclamacion = $this->reclamacion($id);
        $this->authorize('view', $reclamacion);

        $this->resetValidation();
        $this->seleccionada = $reclamacion->id;
        $this->respuesta = '';
        $this->mostrarDetalle = true;
    }

    public function responder(ResponderReclamacion $responder): void
    {
        $reclamacion = $this->reclamacion((int) $this->seleccionada);
        $this->authorize('responder', $reclamacion);

        $this->validate(['respuesta' => ['required', 'string', 'min:20', 'max:5000']], attributes: ['respuesta' => 'respuesta']);

        /** @var User $admin */
        $admin = auth()->user();

        try {
            $responder->ejecutar($reclamacion, $this->respuesta, $admin);
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'respuesta', $e->getMessage());

            return;
        }

        $this->respuesta = '';
        $this->dispatch('reclamacion-respondida');
    }

    public function render(ReclamacionRepository $repositorio, PlazoDeRespuesta $plazo): View
    {
        $resumen = $repositorio->resumen(now());

        return view('livewire.reclamaciones.listado', [
            'filas' => $this->listar($repositorio->consultaListado()),
            // La hoja se lee solo con el diálogo abierto (antes se releía en cada búsqueda o cambio de página).
            'detalle' => $this->seleccionada && $this->mostrarDetalle ? $repositorio->buscar($this->seleccionada)?->load('respondidaPor') : null,
            'plazo' => $plazo,
            'pendientes' => $resumen->pendientes,
            'vencidas' => $resumen->vencidas,
        ]);
    }

    private function reclamacion(int $id): Reclamacion
    {
        $reclamacion = app(ReclamacionRepository::class)->buscar($id);

        abort_if($reclamacion === null, 404);

        return $reclamacion;
    }
}
