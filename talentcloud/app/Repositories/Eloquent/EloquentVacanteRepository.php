<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Tenancy\EmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentVacanteRepository implements VacanteRepository
{
    public function consultaListado(): Builder
    {
        return Vacante::query()->with('empresa')->withCount('postulaciones');
    }

    public function buscar(int $id): ?Vacante
    {
        return Vacante::find($id);
    }

    public function publicadas(): Builder
    {
        return Vacante::sinFiltroDeEmpresa()
            ->where('vacantes.estado', EstadoVacante::Abierta)
            ->whereHas('empresa', fn (Builder $q) => $q->where('estado', EstadoEmpresa::Activa))
            ->with([
                'empresa:id,nombre',
                // Requisito también lleva el filtro de empresa: el postulante no tiene contexto.
                // Solo las columnas públicas (sin el embedding).
                'requisitos' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)
                    ->select(['id', 'vacante_id', 'competencia_id', 'tipo', 'anios_minimos', 'orden'])
                    ->with('competencia:id,nombre')
                    ->orderBy('orden'),
            ]);
    }

    public function buscarPublicada(int $id): ?Vacante
    {
        return $this->publicadas()->find($id);
    }

    public function conDetalle(Vacante $vacante): Vacante
    {
        return $vacante->load(['empresa', 'requisitos.competencia'])->loadCount('postulaciones');
    }

    public function crear(Empresa $empresa, array $datos, ?User $creador): Vacante
    {
        $vacante = new Vacante($datos);
        $vacante->empresa_id = $empresa->id;
        $vacante->creada_por = $creador?->id;
        $vacante->save();

        return $vacante;
    }

    public function actualizar(Vacante $vacante, array $datos): Vacante
    {
        $vacante->update($datos);

        return $vacante;
    }

    public function agregarRequisito(Vacante $vacante, array $datos): Requisito
    {
        $requisito = new Requisito($datos);
        $requisito->vacante_id = $vacante->id;
        $requisito->empresa_id = $vacante->empresa_id;
        $requisito->orden = (int) Requisito::where('vacante_id', $vacante->id)->max('orden') + 1;
        $requisito->save();

        return $requisito;
    }

    public function tieneCompetencia(Vacante $vacante, int $competenciaId, ?int $salvoRequisitoId = null): bool
    {
        return Requisito::where('vacante_id', $vacante->id)
            ->where('competencia_id', $competenciaId)
            ->when($salvoRequisitoId, fn (Builder $q) => $q->whereKeyNot($salvoRequisitoId))
            ->exists();
    }

    public function buscarRequisito(Vacante $vacante, int $requisitoId): ?Requisito
    {
        return Requisito::where('vacante_id', $vacante->id)->find($requisitoId);
    }

    public function empresasDisponibles(): Collection
    {
        return Empresa::where('estado', EstadoEmpresa::Activa)->orderBy('nombre')->get();
    }
}
