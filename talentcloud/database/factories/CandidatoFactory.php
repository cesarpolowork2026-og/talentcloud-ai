<?php

namespace Database\Factories;

use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidato>
 */
class CandidatoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'titular' => fake()->randomElement(['Desarrolladora Backend', 'Analista de Datos', 'Diseñador de Producto', 'QA Engineer']),
            'consentimiento_at' => now(),
        ];
    }

    /** Candidato registrado por una empresa (sin cuenta propia). */
    public function registradoPor(Empresa $empresa): static
    {
        return $this->state(fn () => ['empresa_origen_id' => $empresa->id]);
    }

    /** Candidato con cuenta de postulante. */
    public function conCuenta(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
        ]);
    }
}
