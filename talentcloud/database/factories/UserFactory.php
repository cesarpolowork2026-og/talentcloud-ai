<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function conRol(Rol $rol): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($rol->value));
    }

    public function admin(): static
    {
        return $this->conRol(Rol::Admin);
    }

    /** Cuenta de empresa. Si no se indica con deEmpresa(), crea una empresa. */
    public function empresa(): static
    {
        return $this->conEmpresaPorDefecto()->conRol(Rol::Empresa);
    }

    /** Reclutador. Si no se indica con deEmpresa(), crea una empresa. */
    public function reclutador(): static
    {
        return $this->conEmpresaPorDefecto()->conRol(Rol::Reclutador);
    }

    public function deEmpresa(Empresa $empresa): static
    {
        return $this->state(fn () => ['empresa_id' => $empresa->id]);
    }

    private function conEmpresaPorDefecto(): static
    {
        return $this->state(fn (array $attributes) => [
            'empresa_id' => $attributes['empresa_id'] ?? Empresa::factory(),
        ]);
    }

    public function postulante(): static
    {
        return $this->conRol(Rol::Postulante);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
