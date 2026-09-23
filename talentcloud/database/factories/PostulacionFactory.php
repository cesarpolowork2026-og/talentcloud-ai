<?php

namespace Database\Factories;

use App\Enums\EtapaPostulacion;
use App\Models\Candidato;
use App\Models\Postulacion;
use App\Models\Vacante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Postulacion>
 */
class PostulacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vacante_id' => Vacante::factory(),
            // La empresa de la postulación es la de su vacante.
            'empresa_id' => fn (array $atributos) => Vacante::sinFiltroDeEmpresa()->findOrFail($atributos['vacante_id'])->empresa_id,
            'candidato_id' => Candidato::factory(),
            'etapa' => EtapaPostulacion::Postulado,
            'orden' => 0,
        ];
    }

    public function paraVacante(Vacante $vacante): static
    {
        return $this->state(fn () => ['vacante_id' => $vacante->id, 'empresa_id' => $vacante->empresa_id]);
    }

    public function enEtapa(EtapaPostulacion $etapa): static
    {
        return $this->state(fn () => ['etapa' => $etapa, 'etapa_cambiada_at' => now()]);
    }
}
