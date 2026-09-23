<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la empresa del TenantContext. Falla cerrado: sin contexto
 * (ni empresa ni modo global) la consulta no devuelve filas.
 */
class EmpresaScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $contexto = app(TenantContext::class);

        if ($contexto->esGlobal()) {
            return;
        }

        $columna = $model->qualifyColumn('empresa_id');

        if ($contexto->tieneEmpresa()) {
            $builder->where($columna, $contexto->id());

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
