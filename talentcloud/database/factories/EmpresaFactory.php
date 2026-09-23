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
            'plan' => fake()->randomElement(PlanEmpresa::cases()),
            'estado' => EstadoEmpresa::Activa,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn () => ['estado' => EstadoEmpresa::Suspendida]);
    }
}
