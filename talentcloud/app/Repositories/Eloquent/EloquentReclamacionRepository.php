<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoReclamacion;
use App\Models\Reclamacion;
use App\Repositories\Contracts\ReclamacionRepository;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class EloquentReclamacionRepository implements ReclamacionRepository
{
    public function buscar(int $id): ?Reclamacion
    {
        return Reclamacion::find($id);
    }

    public function consultaListado(): Builder
    {
        return Reclamacion::query();
    }

    public function pendientes(): int
    {
        return Reclamacion::query()->where('estado', EstadoReclamacion::Pendiente)->count();
    }

    public function vencidas(): int
    {
        return Reclamacion::query()
            ->where('estado', EstadoReclamacion::Pendiente)
            ->whereDate('fecha_limite', '<', today())
            ->count();
    }

    public function porVencerHasta(DateTimeInterface $limite): int
    {
        return Reclamacion::query()
            ->where('estado', EstadoReclamacion::Pendiente)
            ->whereDate('fecha_limite', '>=', today())
            ->whereDate('fecha_limite', '<=', $limite)
            ->count();
    }
}
