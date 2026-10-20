<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use Illuminate\Support\Collection;

/** Lo que el admin SaaS ve primero en su panel: las empresas cliente, sus planes y los reclamos. */
final readonly class ResumenDePlataforma
{
    /**
     * @param  array<string, int>  $porPlan  valor de PlanEmpresa => empresas activas con ese plan
     * @param  array<string, list<array{id: int, nombre: string}>>  $empresasPorPlan  valor de PlanEmpresa => esas mismas empresas, por nombre
     * @param  Collection<int, array{empresa: Empresa, uso: UsoDelPlan}>  $cercaDelLimite
     * @param  Collection<int, Empresa>  $solicitudesDePlan  empresas activas que pidieron otro plan, la más antigua primero
     */
    public function __construct(
        public int $empresasActivas,
        public int $empresasSuspendidas,
        public array $porPlan,
        public Collection $cercaDelLimite,
        public int $enPrueba = 0,
        public Collection $solicitudesDePlan = new Collection,
        public ?ResumenDeReclamaciones $reclamaciones = null,
        public array $empresasPorPlan = [],
    ) {}

    public function empresasConPlan(PlanEmpresa $plan): int
    {
        return $this->porPlan[$plan->value] ?? 0;
    }

    /** @return list<array{id: int, nombre: string}> */
    public function nombresConPlan(PlanEmpresa $plan): array
    {
        return $this->empresasPorPlan[$plan->value] ?? [];
    }
}
