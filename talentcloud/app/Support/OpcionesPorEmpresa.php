<?php

namespace App\Support;

use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Collection;

/**
 * Opciones de un selector (<x-ui.select>) cuyas filas pertenecen a una
 * vacante: vacantes, postulaciones… En la vista global (admin SaaS) se
 * agrupan por empresa ([empresa => [id => etiqueta]], un <optgroup> por
 * empresa, ordenadas por nombre); para el personal de una empresa, que solo
 * ve lo suyo, la lista es plana ([id => etiqueta]).
 *
 * Cargar vacante.empresa con with() antes de llamar.
 */
final class OpcionesPorEmpresa
{
    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $filas
     * @param  Closure(T): ?Vacante  $vacanteDe
     * @param  Closure(T): string  $etiqueta
     * @return array<int|string, string|array<int, string>>
     */
    public static function de(Collection $filas, Closure $vacanteDe, Closure $etiqueta): array
    {
        $plana = fn (Collection $grupo) => $grupo->mapWithKeys(fn ($fila) => [$fila->getKey() => $etiqueta($fila)])->all();

        if (! app(TenantContext::class)->esGlobal()) {
            return $plana($filas);
        }

        return $filas
            ->groupBy(fn ($fila) => $vacanteDe($fila)?->empresa->nombre ?? 'Sin empresa')
            ->sortKeys()
            ->map($plana)
            ->all();
    }
}
