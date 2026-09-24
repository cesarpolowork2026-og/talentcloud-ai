<?php

namespace App\Services\Candidatos;

use App\Enums\FuenteCompetencia;
use App\Ia\CompetenciaExtraida;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Competencias del candidato. Las manuales son anotaciones de la empresa en
 * curso: se agregan y quitan una a una y solo las ve esa empresa. Las
 * extraídas del CV por la IA (fuente = extraccion, sin empresa) no se editan
 * a mano: se reemplazan en bloque con cada análisis.
 */
class CompetenciasDelCandidato
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function agregar(Candidato $candidato, string $nombre): Competencia
    {
        $competencia = Competencia::obtenerOCrear($nombre);

        if (! $this->manualesDeLaEmpresa($candidato)->whereKey($competencia->id)->exists()) {
            $candidato->todasLasCompetencias()->attach($competencia->id, [
                'fuente' => FuenteCompetencia::Manual->value,
                'empresa_id' => $this->contexto->id(),
            ]);
        }

        return $competencia;
    }

    public function quitar(Candidato $candidato, int $competenciaId): void
    {
        $this->manualesDeLaEmpresa($candidato)->detach($competenciaId);
    }

    /**
     * Sustituye las competencias extraídas del CV (las manuales no se tocan).
     *
     * @param  list<CompetenciaExtraida>  $extraidas
     */
    public function reemplazarExtraidas(int $candidatoId, array $extraidas): void
    {
        $candidato = Candidato::findOrFail($candidatoId);

        $candidato->todasLasCompetencias()->wherePivot('fuente', FuenteCompetencia::Extraccion->value)->detach();

        $candidato->todasLasCompetencias()->attach(collect($extraidas)->mapWithKeys(fn (CompetenciaExtraida $c) => [
            $c->competenciaId => [
                'fuente' => FuenteCompetencia::Extraccion->value,
                'anios' => $c->anios,
                'evidencia' => $c->evidencia,
            ],
        ])->all());
    }

    /**
     * Manuales de la empresa en curso (sin empresa, en modo global).
     *
     * @return BelongsToMany<Competencia, Candidato>
     */
    private function manualesDeLaEmpresa(Candidato $candidato): BelongsToMany
    {
        $relacion = $candidato->todasLasCompetencias()->wherePivot('fuente', FuenteCompetencia::Manual->value);
        $empresaId = $this->contexto->id();

        return $empresaId === null
            ? $relacion->wherePivotNull('empresa_id')
            : $relacion->wherePivot('empresa_id', $empresaId);
    }
}
