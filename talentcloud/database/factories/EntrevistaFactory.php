<?php

namespace Database\Factories;

use App\Enums\EstadoEntrevista;
use App\Enums\ModalidadEntrevista;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrevista>
 */
class EntrevistaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'postulacion_id' => Postulacion::factory(),
            'empresa_id' => fn (array $atributos) => Postulacion::sinFiltroDeEmpresa()->findOrFail($atributos['postulacion_id'])->empresa_id,
            'entrevistador_id' => fn (array $atributos) => User::factory()->reclutador()->state(['empresa_id' => $atributos['empresa_id']]),
            'inicia_at' => now()->addDay()->setTime(10, 0),
            'duracion_min' => 45,
            'modalidad' => ModalidadEntrevista::Videollamada,
            'estado' => EstadoEntrevista::Pendiente,
        ];
    }
}
