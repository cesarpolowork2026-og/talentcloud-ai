<?php

namespace Database\Factories;

use App\Enums\EstadoEvaluacion;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluacion>
 */
class EvaluacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $criterios = [
            ['nombre' => 'Conocimiento técnico', 'puntaje' => 8],
            ['nombre' => 'Comunicación', 'puntaje' => 7],
        ];

        return [
            'postulacion_id' => Postulacion::factory(),
            'empresa_id' => fn (array $atributos) => Postulacion::sinFiltroDeEmpresa()->findOrFail($atributos['postulacion_id'])->empresa_id,
            'evaluador_id' => fn (array $atributos) => User::factory()->reclutador()->state(['empresa_id' => $atributos['empresa_id']]),
            'criterios' => $criterios,
            'puntaje' => Evaluacion::promediar($criterios),
            'estado' => EstadoEvaluacion::Pendiente,
        ];
    }
}
