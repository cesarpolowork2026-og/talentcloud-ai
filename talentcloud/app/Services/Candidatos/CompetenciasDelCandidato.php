<?php

namespace App\Services\Candidatos;

use App\Enums\FuenteCompetencia;
use App\Ia\CompetenciaExtraida;
use App\Models\Candidato;
use App\Models\Competencia;

/**
 * Competencias del candidato. Las manuales se agregan y quitan una a una;
 * las extraídas del CV por la IA (fuente = extraccion) no se editan a mano:
 * se reemplazan en bloque con cada análisis.
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

    /**
     * Sustituye las competencias extraídas del CV (las manuales no se tocan).
     *
     * @param  list<CompetenciaExtraida>  $extraidas
     */
    public function reemplazarExtraidas(int $candidatoId, array $extraidas): void
    {
        $candidato = Candidato::findOrFail($candidatoId);

        $candidato->competencias()->wherePivot('fuente', FuenteCompetencia::Extraccion->value)->detach();

        $candidato->competencias()->attach(collect($extraidas)->mapWithKeys(fn (CompetenciaExtraida $c) => [
            $c->competenciaId => [
                'fuente' => FuenteCompetencia::Extraccion->value,
                'anios' => $c->anios,
                'evidencia' => $c->evidencia,
            ],
        ])->all());
    }
}
