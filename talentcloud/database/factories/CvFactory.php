<?php

namespace Database\Factories;

use App\Enums\EstadoProcesamientoCv;
use App\Models\Candidato;
use App\Models\Cv;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cv>
 */
class CvFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidato_id' => Candidato::factory(),
            'disco' => 'cvs',
            'ruta' => 'cvs/'.fake()->uuid().'.pdf',
            'nombre_original' => 'CV.pdf',
            'mime' => 'application/pdf',
            'tamano' => 120_000,
            'estado_procesamiento' => EstadoProcesamientoCv::Pendiente,
        ];
    }

    public function procesado(string $texto = 'Experiencia en desarrollo backend.'): static
    {
        return $this->state(fn () => [
            'texto_extraido' => $texto,
            'estado_procesamiento' => EstadoProcesamientoCv::Procesado,
            'procesado_at' => now(),
        ]);
    }

    public function conError(string $mensaje = 'No se pudo leer el archivo.'): static
    {
        return $this->state(fn () => [
            'estado_procesamiento' => EstadoProcesamientoCv::Error,
            'error' => $mensaje,
        ]);
    }
}
