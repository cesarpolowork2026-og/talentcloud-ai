<?php

namespace App\Services\Candidatos;

use App\Enums\FuenteCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;

/**
 * Competencias registradas a mano. Las extraídas del CV por la IA
 * (fuente = extraccion) no se editan aquí: se regeneran con cada análisis.
 */
class CompetenciasDelCandidato
{
    public function agregar(Candidato $candidato, string $nombre): Competencia
    {
        $competencia = Competencia::obtenerOCrear($nombre);

        $yaExiste = $candidato->competencias()
            ->wherePivot('fuente', FuenteCompetencia::Manual->value)
            ->whereKey($competencia->id)
            ->exists();

        if (! $yaExiste) {
            $candidato->competencias()->attach($competencia->id, ['fuente' => FuenteCompetencia::Manual->value]);
        }

        return $competencia;
    }

    public function quitar(Candidato $candidato, int $competenciaId): void
    {
        $candidato->competencias()
            ->wherePivot('fuente', FuenteCompetencia::Manual->value)
            ->detach($competenciaId);
    }
}
