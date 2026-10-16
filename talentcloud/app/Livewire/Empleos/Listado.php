<?php

namespace App\Livewire\Empleos;

use App\Enums\Modalidad;
use App\Livewire\Concerns\WithListado;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Bolsa pública de empleos (/empleos), sin iniciar sesión: las vacantes
 * abiertas de las empresas activas. Decisión del plan: una vacante abierta es
 * pública. Solo usa VacanteRepository::publicadas() (columnas públicas).
 */
#[Layout('layouts.publico')]
#[Title('Empleos')]
class Listado extends Component
{
    use WithListado;

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['titulo', 'ubicacion', 'empresa.nombre'];
    }

    /** @return array<string, string> */
    protected function opcionesEstado(): array
    {
        return Modalidad::opciones();
    }

    protected function columnaEstado(): string
    {
        return 'modalidad';
    }

    protected function porPagina(): int
    {
        return 12;
    }

    public function render(VacanteRepository $vacantes): View
    {
        return view('livewire.empleos.listado', [
            'filas' => $this->listar($vacantes->publicadasParaListado()->latest('publicada_at')),
            'modalidades' => Modalidad::opciones(),
        ])->layoutData(['descripcion' => 'Vacantes abiertas de las empresas en TalentCloud AI. Regístrate, sube tu CV una vez y postula en un clic.']);
    }
}
