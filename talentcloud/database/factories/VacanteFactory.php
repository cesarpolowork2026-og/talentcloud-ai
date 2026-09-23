<?php

namespace Database\Factories;

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Models\Empresa;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacante>
 */
class VacanteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'titulo' => fake()->randomElement(['Desarrollador Backend', 'Product Designer', 'Data Analyst', 'QA Engineer', 'DevOps Engineer']),
            'descripcion' => fake()->paragraph(),
            'ubicacion' => fake()->randomElement(['Remoto', 'Madrid', 'Bogotá', 'Lima']),
            'modalidad' => Modalidad::Remoto,
            'estado' => EstadoVacante::Abierta,
            'publicada_at' => now(),
        ];
    }

    public function deEmpresa(Empresa $empresa): static
    {
        return $this->state(fn () => ['empresa_id' => $empresa->id]);
    }

    public function enEstado(EstadoVacante $estado): static
    {
        return $this->state(fn () => [
            'estado' => $estado,
            'publicada_at' => $estado === EstadoVacante::Borrador ? null : now(),
        ]);
    }
}
