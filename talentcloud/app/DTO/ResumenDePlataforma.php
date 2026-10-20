<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;
use App\Models\Auditoria;
use App\Models\Empresa;
use Illuminate\Support\Collection;

/** Lo que el admin SaaS ve primero en su panel: el negocio (empresas, planes, ingresos), el crecimiento y el estado del sistema. */
final readonly class ResumenDePlataforma
{
    /**
     * @param  array<string, int>  $porPlan  valor de PlanEmpresa => empresas activas con ese plan
     * @param  array<string, list<array{id: int, nombre: string}>>  $empresasPorPlan  valor de PlanEmpresa => esas mismas empresas, por nombre
     * @param  Collection<int, array{empresa: Empresa, uso: UsoDelPlan}>  $cercaDelLimite
     * @param  Collection<int, Empresa>  $solicitudesDePlan  empresas activas que pidieron otro plan, la más antigua primero
     * @param  list<array{semana: string, empresas: int, postulaciones: int}>  $crecimiento  últimas semanas, de la más antigua a la actual
     * @param  Collection<int, Auditoria>  $ultimasAcciones
     */
    public function __construct(
        public int $empresasActivas,
        public int $empresasSuspendidas,
        public array $porPlan,
        public Collection $cercaDelLimite,
        public int $enPrueba = 0,
        public Collection $solicitudesDePlan = new Collection,
        public array $empresasPorPlan = [],
        public int $usuariosDeEmpresas = 0,
        public int $postulantes = 0,
        public int $vacantesActivas = 0,
        public int $postulacionesDelMes = 0,
        public int $ingresoMensual = 0,
        public int $enterpriseSinPrecio = 0,
        public array $crecimiento = [],
        public int $trabajosFallidos = 0,
        public int $trabajosEnCola = 0,
        public int $cvConError = 0,
        public Collection $ultimasAcciones = new Collection,
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

    public function sistemaSano(): bool
    {
        return $this->trabajosFallidos === 0 && $this->cvConError === 0;
    }
}
