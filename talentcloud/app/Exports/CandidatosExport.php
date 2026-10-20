<?php

namespace App\Exports;

use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Postulacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Candidatos visibles para la empresa, una fila por postulación.
 * Un candidato registrado sin postular aparece en una fila sin vacante.
 *
 * @extends ExportacionDeListado<Candidato>
 */
class CandidatosExport extends ExportacionDeListado
{
    public function headings(): array
    {
        return ['Candidato', 'Correo', 'Titular', 'Vacante', 'Etapa', 'Competencias', 'Postulación'];
    }

    protected function titulo(): string
    {
        return 'Candidatos';
    }

    /** Competencias anotadas por la empresa. */
    protected function columnasDeTexto(): array
    {
        return [5];
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
            ->unique('id')
            ->map(fn (Competencia $c) => $c->nombre)
            ->join(', ');

        $comun = [$candidato->nombre, $candidato->email, $candidato->titular];

        if ($candidato->postulaciones->isEmpty()) {
            return [[...$comun, null, null, $competencias, null]];
        }

        return $candidato->postulaciones->map(fn (Postulacion $p) => [
            ...$comun,
            $p->vacante?->titulo,
            $p->etapa->etiqueta(),
            $competencias,
            self::fecha($p->created_at),
        ])->values()->all();
    }
}
