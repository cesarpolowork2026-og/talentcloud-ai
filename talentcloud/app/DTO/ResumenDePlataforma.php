<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use Illuminate\Support\Collection;

/** Lo que el admin SaaS ve primero en su panel: las empresas cliente y la salud de la IA. */
final readonly class ResumenDePlataforma
{
    /**
     * @param  array<string, int>  $porPlan  valor de PlanEmpresa => empresas activas con ese plan
     * @param  Collection<int, array{empresa: Empresa, uso: UsoDelPlan}>  $cercaDelLimite
     */
    public function __construct(
        public int $empresasActivas,
        public int $empresasSuspendidas,
        public array $porPlan,
        public Collection $cercaDelLimite,
        public ResumenCalidadIa $ia,
    ) {}

    public function empresasConPlan(PlanEmpresa $plan): int
    {
        return $this->porPlan[$plan->value] ?? 0;
    }
}
