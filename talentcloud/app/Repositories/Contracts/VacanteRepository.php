<?php

namespace App\Repositories\Contracts;

use App\Models\Empresa;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface VacanteRepository
{
    /**
     * Consulta base del listado "Vacantes", con la empresa y postulaciones_count.
     *
     * @return Builder<Vacante>
     */
    public function consultaListado(): Builder;

    public function buscar(int $id): ?Vacante;

    /**
     * Vacantes que ve un postulante: abiertas y de empresas activas, de
     * cualquier empresa, con la empresa y los requisitos. Es la única lectura
     * de vacantes entre empresas y solo expone datos públicos (nunca
     * postulaciones ni conteos de candidatos).
     *
     * @return Builder<Vacante>
     */
    public function publicadas(): Builder;

    /** Una vacante de publicadas(), o null si no existe o ya no es pública. */
    public function buscarPublicada(int $id): ?Vacante;

    /** Si la vacante está en publicadas(), sin cargarla (una sola consulta). */
    public function existePublicada(int $id): bool;

    /** Carga empresa, requisitos (con su competencia) y postulaciones_count. */
    public function conDetalle(Vacante $vacante): Vacante;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Empresa $empresa, array $datos, ?User $creador): Vacante;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Vacante $vacante, array $datos): Vacante;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function agregarRequisito(Vacante $vacante, array $datos): Requisito;

    public function tieneCompetencia(Vacante $vacante, int $competenciaId, ?int $salvoRequisitoId = null): bool;

    public function buscarRequisito(Vacante $vacante, int $requisitoId): ?Requisito;

    /**
     * Empresas activas para el selector del admin SaaS.
     *
     * @return Collection<int, Empresa>
     */
    public function empresasDisponibles(): Collection;
}
