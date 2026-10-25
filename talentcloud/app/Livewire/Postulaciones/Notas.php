<?php

namespace App\Livewire\Postulaciones;

use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Postulaciones\NotasDePostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Notas del equipo" de una postulación: se usa en el detalle de la postulación y en la ficha
 * del candidato. Solo el personal de la empresa (PostulacionPolicy::gestionarNotas).
 */
class Notas extends Component
{
    use SeActualizaSola;

    #[Locked]
    public int $postulacionId;

    public string $texto = '';

    public function mount(int $postulacionId): void
    {
        $this->postulacionId = $postulacionId;
        $this->authorize('gestionarNotas', $this->postulacion());
    }

    public function agregar(NotasDePostulacion $notas): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('gestionarNotas', $postulacion);

        $this->validate(['texto' => ['required', 'string', 'max:'.NotasDePostulacion::MAXIMO_CARACTERES]], attributes: ['texto' => 'nota']);

        try {
            $notas->agregar($postulacion, $this->usuario(), $this->texto);
        } catch (OperacionNoPermitida $e) {
            $this->addError('texto', $e->getMessage());

            return;
        }

        $this->reset('texto');
    }

    public function borrar(int $notaId, NotasDePostulacion $notas): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('gestionarNotas', $postulacion);

        $nota = Nota::where('postulacion_id', $postulacion->id)->find($notaId);
        abort_if($nota === null, 404);
        $this->authorize('delete', $nota);

        $notas->borrar($nota, $this->usuario());
    }

    /** Notas que escribe otra persona del equipo. */
    protected function partesDeLaFirma(): array
    {
        return [$this->parteDe(Nota::query()->where('postulacion_id', $this->postulacionId))];
    }

    public function render(): View
    {
        return view('livewire.postulaciones.notas', [
            'notas' => $this->postulacion()->notas()->with('autor:id,name')->get(),
            'usuarioId' => $this->usuario()->id,
        ]);
    }

    private function postulacion(): Postulacion
    {
        // Con el filtro por empresa, una postulación ajena no se encuentra.
        $postulacion = Postulacion::find($this->postulacionId);
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
