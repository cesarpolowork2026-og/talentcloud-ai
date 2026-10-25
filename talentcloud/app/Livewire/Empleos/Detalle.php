<?php

namespace App\Livewire\Empleos;

use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Support\DatosEstructurados;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle público de una vacante abierta (/empleos/{id}). "Postular" pasa por
 * empleos.postular, que lleva a iniciar sesión o registrarse y vuelve a la
 * vacante. Si no es pública (cerrada, borrador o empresa suspendida): 404.
 */
#[Layout('layouts.publico')]
class Detalle extends Component
{
    use SeActualizaSola;

    #[Locked]
    public int $vacanteId;

    public function mount(int $id, VacanteRepository $vacantes): void
    {
        // Solo se comprueba aquí: render() la carga una vez, con sus requisitos.
        abort_unless($vacantes->existePublicada($id), 404);

        $this->vacanteId = $id;
    }

    /** La vacante pública y sus requisitos (si la empresa la edita o la cierra mientras se mira). */
    protected function partesDeLaFirma(): array
    {
        return [
            $this->parteDeFila(Vacante::sinFiltroDeEmpresa(), $this->vacanteId),
            $this->parteDe(Requisito::sinFiltroDeEmpresa()->where('vacante_id', $this->vacanteId)),
        ];
    }

    public function render(VacanteRepository $vacantes): View
    {
        $vacante = $vacantes->buscarPublicada($this->vacanteId);

        return view('livewire.empleos.detalle', ['vacante' => $vacante])
            ->title($vacante->titulo ?? 'Empleo')
            ->layoutData([
                'descripcion' => $vacante === null ? null
                    : $vacante->titulo.' en '.$vacante->empresa?->nombre.' · '.$vacante->lugarYModalidad().'. Postula con tu CV en TalentCloud AI.',
                // La oferta para Google for Jobs: solo de una vacante pública (cerrada o suspendida no llega aquí).
                'jsonld' => $vacante === null ? null : DatosEstructurados::json(DatosEstructurados::ofertaDeEmpleo($vacante)),
            ]);
    }
}
