<?php

namespace App\Services\Vacantes;

use App\Enums\EstadoVacante;
use App\Models\Vacante;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de vacantes para los buscadores de las pantallas (no un desplegable con todas): hasta
 * `$limite` coincidencias de las que el usuario puede ver, por título, sin importar tildes. Una
 * empresa puede tener miles de vacantes; enviarlas todas al navegador no escala.
 */
class BuscadorDeVacantes
{
    public const LIMITE = 20;

    /**
     * @param  Builder<Vacante>|null  $base  Para acotar más (por ejemplo, las que tienen evaluaciones).
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    public function buscar(string $texto, bool $soloActivas = false, ?Builder $base = null, int $limite = self::LIMITE): array
    {
        $texto = trim($texto);

        return ($base ?? Vacante::query())
            ->with('empresa:id,nombre')
            ->when($soloActivas, fn (Builder $q) => $q->whereIn('estado', EstadoVacante::activos()))
            ->when($texto !== '', fn (Builder $q) => $q
                ->whereRaw('unaccent(vacantes.titulo) ilike unaccent(?)', ['%'.addcslashes($texto, '%_\\').'%'])
                ->orderBy('vacantes.titulo'), fn (Builder $q) => $q->latest('vacantes.id'))
            ->limit($limite)
            ->get(['vacantes.id', 'vacantes.titulo', 'vacantes.empresa_id', 'vacantes.estado'])
            ->map(fn (Vacante $v) => [
                'id' => $v->id,
                'etiqueta' => Formato::vacante($v),
                'detalle' => $v->estado->etiqueta(),
            ])
            ->all();
    }

    /** Lo que se escribe en el campo cuando ya hay una vacante elegida. */
    public function etiquetaDe(?int $id): string
    {
        $vacante = $id ? Vacante::query()->with('empresa:id,nombre')->find($id) : null;

        return $vacante ? Formato::vacante($vacante) : '';
    }
}
