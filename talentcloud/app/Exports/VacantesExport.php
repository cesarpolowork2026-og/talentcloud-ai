<?php

namespace App\Exports;

use App\Models\Vacante;

/**
 * Vacantes (con empresa y postulaciones_count, como el listado).
 *
 * @extends ExportacionDeListado<Vacante>
 */
class VacantesExport extends ExportacionDeListado
{
    public function headings(): array
    {
        return ['Vacante', 'Empresa', 'Ubicación', 'Modalidad', 'Estado', 'Candidatos', 'Creada', 'Publicada'];
    }

    protected function titulo(): string
    {
        return 'Vacantes';
    }

    /**
     * @param  Vacante  $vacante
     * @return list<mixed>
     */
    public function map($vacante): array
    {
        return [
            $vacante->titulo,
            $vacante->empresa?->nombre,
            // En una vacante remota no hay ubicación: la modalidad lo dice y, si la hay, la cobertura.
            $vacante->modalidad->requiereUbicacion() ? $vacante->ubicacion : ($vacante->ubicacion ? 'Cobertura: '.$vacante->ubicacion : null),
            $vacante->modalidad->etiqueta(),
            $vacante->estado->etiqueta(),
            (int) $vacante->postulaciones_count,
            self::fecha($vacante->created_at),
            self::fecha($vacante->publicada_at),
        ];
    }
}
