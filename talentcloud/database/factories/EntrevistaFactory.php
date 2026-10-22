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
    /** Una entrevista presencial, con su dirección. */
    public function presencial(string $lugar = 'Av. Larco 1301, Miraflores'): static
    {
        return $this->state(fn () => ['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => $lugar]);
    }

    /** Una entrevista telefónica, con su indicación. */
    public function telefonica(string $lugar = 'Te llamaremos al +51 999 999 999'): static
    {
        return $this->state(fn () => ['modalidad' => ModalidadEntrevista::Telefonica, 'lugar' => $lugar]);
    }

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
