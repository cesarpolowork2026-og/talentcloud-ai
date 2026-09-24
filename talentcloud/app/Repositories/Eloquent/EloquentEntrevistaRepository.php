<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Repositories\Contracts\EntrevistaRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentEntrevistaRepository implements EntrevistaRepository
{
    public function consultaListado(): Builder
    {
        return Entrevista::query()->with(['postulacion.candidato', 'postulacion.vacante', 'entrevistador']);
    }

    public function buscar(int $id): ?Entrevista
    {
        return $this->consultaListado()->find($id);
    }

    public function postulacionesAgendables(): Collection
    {
        return Postulacion::query()
            ->with(['candidato', 'vacante'])
            ->where('etapa', '!=', EtapaPostulacion::Descartado)
            ->whereHas('vacante', fn (Builder $q) => $q->whereIn('estado', [EstadoVacante::Abierta, EstadoVacante::EnRevision]))
            ->get()
            ->sortBy(fn (Postulacion $p) => $p->candidato?->nombre)
            ->values();
    }

    public function haySolapamiento(int $entrevistadorId, CarbonInterface $inicio, CarbonInterface $fin): bool
    {
        // Dos intervalos se cruzan si cada uno empieza antes de que termine el otro.
        return Entrevista::query()
            ->where('entrevistador_id', $entrevistadorId)
            ->where('estado', '!=', EstadoEntrevista::Cancelada)
            ->where('inicia_at', '<', $fin)
            ->whereRaw('inicia_at + make_interval(mins => duracion_min) > ?', [$inicio])
            ->exists();
    }

    public function crear(Postulacion $postulacion, array $datos): Entrevista
    {
        $entrevista = new Entrevista(['postulacion_id' => $postulacion->id, ...$datos]);
        $entrevista->empresa_id = $postulacion->empresa_id;
        $entrevista->save();

        return $entrevista;
    }
}
