<?php

namespace Database\Factories;

use App\Enums\TipoRequisito;
use App\Models\Competencia;
use App\Models\Requisito;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisito>
 */
class RequisitoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vacante_id' => Vacante::factory(),
            // La empresa del requisito es la de su vacante.
            'empresa_id' => fn (array $atributos) => Vacante::sinFiltroDeEmpresa()->findOrFail($atributos['vacante_id'])->empresa_id,
            'competencia_id' => Competencia::factory(),
            'tipo' => TipoRequisito::Obligatorio,
            'peso' => 3,
        ];
    }
}
