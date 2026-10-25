<?php

namespace App\Livewire\Plataforma;

use App\Livewire\Concerns\SeActualizaSola;
use App\Livewire\Concerns\WithListado;
use App\Models\Auditoria as RegistroDeAccion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * «Registro de acciones» del admin SaaS: todo lo que se hizo sobre empresas, usuarios y el catálogo de competencias,
 * de la más reciente a la más antigua.
 */
#[Title('Registro de acciones')]
class Auditoria extends Component
{
    use SeActualizaSola;
    use WithListado;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);

        $this->direccion = 'desc';
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['descripcion', 'usuario_nombre', 'empresa.nombre'];
    }

    protected function porPagina(): int
    {
        return 20;
    }

    /** Acciones nuevas del registro de auditoría (solo se agregan: basta la fecha de creación). */
    protected function partesDeLaFirma(): array
    {
        return [$this->parteDe(RegistroDeAccion::query(), 'created_at')];
    }

    public function render(): View
    {
        return view('livewire.plataforma.auditoria', [
            'filas' => $this->listar(RegistroDeAccion::query()->with('empresa:id,nombre')->latest('created_at')->latest('id')),
        ]);
    }
}
