<?php

namespace Database\Factories;

use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'sector' => fake()->randomElement(['Tecnología', 'Diseño', 'Consultoría', 'Finanzas']),
            // Gratis (1 vacante, 2 usuarios) se elige a propósito en los tests que lo necesitan.
            'plan' => fake()->randomElement([PlanEmpresa::Starter, PlanEmpresa::Pro, PlanEmpresa::Enterprise]),
            'estado' => EstadoEmpresa::Activa,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn () => ['estado' => EstadoEmpresa::Suspendida]);
    }
}
