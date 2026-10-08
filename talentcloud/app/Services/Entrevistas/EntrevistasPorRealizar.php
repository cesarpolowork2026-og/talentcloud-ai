<?php

namespace App\Services\Entrevistas;

use App\Enums\EstadoEntrevista;
use App\Models\Entrevista;
use Illuminate\Database\Eloquent\Builder;

/**
 * Entrevistas pendientes o confirmadas: las que aún pueden celebrarse. Antes
 * de dejar a alguien sin proceso (descartar, cerrar la vacante, desactivar al
 * entrevistador) hay que resolverlas, para que nadie espere una entrevista que
 * no va a ocurrir. Se cuentan sin filtro de empresa: quien llama ya acota.
 */
class EntrevistasPorRealizar
{
    /** @return Builder<Entrevista> */
    public static function consulta(): Builder
    {
        return Entrevista::sinFiltroDeEmpresa()
            ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada]);
    }

    /** "1 entrevista" / "2 entrevistas". */
    public static function texto(int $cuantas): string
    {
        return $cuantas === 1 ? '1 entrevista' : "{$cuantas} entrevistas";
    }

    /** Concuerda el texto con el número: "Cancélala" / "Cancélalas". */
    public static function segun(int $cuantas, string $una, string $varias): string
    {
        return $cuantas === 1 ? $una : $varias;
    }
}
