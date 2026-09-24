<?php

namespace App\Exports;

use App\Enums\FuenteCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Postulacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Candidatos visibles para la empresa, una fila por postulación (con su match).
 * Un candidato registrado sin postular aparece en una fila sin vacante.
 *
 * @extends ExportacionDeListado<Candidato>
 */
class CandidatosExport extends ExportacionDeListado
{
    public function headings(): array
    {
        return ['Candidato', 'Correo', 'Titular', 'Vacante', 'Etapa', 'Match %', 'Análisis', 'Competencias extraídas por IA', 'Postulación'];
    }

    /**
     * Carga de una vez lo que usa cada fila (sin N+1). Las postulaciones pasan por
     * el filtro de empresa: cada empresa exporta solo las suyas.
     *
     * @return Builder<Candidato>
     */
    public function query(): Builder
    {
        return parent::query()->with(['postulaciones.vacante', 'competencias']);
    }

    /**
     * @param  Candidato  $candidato
     * @return list<list<mixed>>
     */
    public function map($candidato): array
    {
        $competencias = $candidato->competencias
            ->where('pivot.fuente', FuenteCompetencia::Extraccion->value)
            ->unique('id')
            ->map(fn (Competencia $c) => $c->nombre)
            ->join(', ');

        $comun = [$candidato->nombre, $candidato->email, $candidato->titular];

        if ($candidato->postulaciones->isEmpty()) {
            return [[...$comun, null, null, null, null, $competencias, null]];
        }

        return $candidato->postulaciones->map(fn (Postulacion $p) => [
            ...$comun,
            $p->vacante?->titulo,
            $p->etapa->etiqueta(),
            $p->matchPorcentaje(),
            $p->match_motor?->etiqueta(),
            $competencias,
            self::fecha($p->created_at),
        ])->values()->all();
    }
}
