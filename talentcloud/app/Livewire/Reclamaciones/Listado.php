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
    use WithListado;

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
        return view('livewire.reclamaciones.listado', [
            'filas' => $this->listar($repositorio->consultaListado()),
            'detalle' => $this->seleccionada ? $repositorio->buscar($this->seleccionada)?->load('respondidaPor') : null,
            'plazo' => $plazo,
            'pendientes' => $repositorio->pendientes(),
            'vencidas' => $repositorio->vencidas(),
        ]);
    }

    private function reclamacion(int $id): Reclamacion
    {
        $reclamacion = app(ReclamacionRepository::class)->buscar($id);

        abort_if($reclamacion === null, 404);

        return $reclamacion;
    }
}
