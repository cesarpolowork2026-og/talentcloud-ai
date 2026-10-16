<?php

namespace App\Repositories\Eloquent;

use App\DTO\ResumenDeReclamaciones;
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

    public function resumen(DateTimeInterface $limite): ResumenDeReclamaciones
    {
        $fila = Reclamacion::query()
            ->where('estado', EstadoReclamacion::Pendiente)
            ->selectRaw('COUNT(*) AS pendientes')
            ->selectRaw('COUNT(*) FILTER (WHERE fecha_limite::date < ?) AS vencidas', [today()])
            ->selectRaw('COUNT(*) FILTER (WHERE fecha_limite::date >= ? AND fecha_limite::date <= ?) AS por_vencer', [today(), $limite])
            ->toBase()
            ->first();

        return new ResumenDeReclamaciones(
            pendientes: (int) ($fila->pendientes ?? 0),
            vencidas: (int) ($fila->vencidas ?? 0),
            porVencer: (int) ($fila->por_vencer ?? 0),
        );
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
