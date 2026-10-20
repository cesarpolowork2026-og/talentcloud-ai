<?php

namespace App\Services\Candidatos;

use App\Enums\FuenteCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Postulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Competencias del candidato: anotaciones de la empresa en curso. Se agregan y
 * quitan una a una y solo las ve esa empresa.
 *
 * El admin SaaS indica para qué empresa anota o quita, y debe ser una en la que
 * el candidato tenga un proceso.
 */
class CompetenciasDelCandidato
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function agregar(Candidato $candidato, string $nombre, ?int $paraEmpresaId = null): Competencia
    {
        $empresaId = $this->empresaDestino($candidato, $paraEmpresaId);
        $competencia = Competencia::obtenerOCrear($nombre);

        if (! $this->manualesDe($candidato, $empresaId)->whereKey($competencia->id)->exists()) {
            $candidato->todasLasCompetencias()->attach($competencia->id, [
                'fuente' => FuenteCompetencia::Manual->value,
                'empresa_id' => $empresaId,
            ]);
        }

        return $competencia;
    }

    public function quitar(Candidato $candidato, int $competenciaId, ?int $deEmpresaId = null): void
    {
        $this->manualesDe($candidato, $this->empresaDestino($candidato, $deEmpresaId))->detach($competenciaId);
    }

    /**
     * Empresa sobre la que se anota: la del contexto; en el contexto global
     * (admin SaaS), la indicada, que debe tener un proceso del candidato.
     */
    private function empresaDestino(Candidato $candidato, ?int $empresaIndicada): ?int
    {
        if ($this->contexto->tieneEmpresa()) {
            if ($empresaIndicada !== null && $empresaIndicada !== $this->contexto->id()) {
                throw new OperacionNoPermitida('Solo puedes anotar competencias para tu empresa.', 'nuevaCompetencia');
            }

            return $this->contexto->id();
        }

        if ($empresaIndicada === null) {
            return null;
        }

        $tieneProceso = Postulacion::sinFiltroDeEmpresa()
            ->where('candidato_id', $candidato->id)
            ->where('empresa_id', $empresaIndicada)
            ->exists();

        if (! $tieneProceso) {
            throw new OperacionNoPermitida('El candidato no tiene procesos en esa empresa.', 'empresaCompetencia');
        }

        return $empresaIndicada;
    }

    /**
     * Manuales de una empresa (las sin empresa, si es null).
     *
     * @return BelongsToMany<Competencia, Candidato>
     */
    private function manualesDe(Candidato $candidato, ?int $empresaId): BelongsToMany
    {
        $relacion = $candidato->todasLasCompetencias()->wherePivot('fuente', FuenteCompetencia::Manual->value);

        return $empresaId === null
            ? $relacion->wherePivotNull('empresa_id')
            : $relacion->wherePivot('empresa_id', $empresaId);
    }
}
