<?php

namespace App\Tenancy;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para modelos cuyas filas pertenecen a una empresa (columna empresa_id):
 * - aplica EmpresaScope a todas las consultas;
 * - al crear, toma empresa_id del TenantContext si no viene dado;
 * - impide crear filas sin empresa.
 *
 * @mixin Model
 */
trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope);

        static::creating(function (Model $model): void {
            $contexto = app(TenantContext::class);

            if (empty($model->getAttribute('empresa_id'))) {
                $model->setAttribute('empresa_id', $contexto->id());
            }

            if (empty($model->getAttribute('empresa_id'))) {
                throw new SinContextoDeEmpresa(
                    'No se puede crear '.class_basename($model).' sin una empresa: falta el contexto de tenant.'
                );
            }

            // Dentro del contexto de una empresa no se pueden crear filas de otra.
            if ($contexto->tieneEmpresa() && (int) $model->getAttribute('empresa_id') !== $contexto->id()) {
                throw new SinContextoDeEmpresa(
                    'No se puede crear '.class_basename($model).' para otra empresa.'
                );
            }
        });

        // empresa_id no cambia después de creado: un registro no se "muda" de empresa.
        static::updating(function (Model $model): void {
            if ($model->isDirty('empresa_id')) {
                throw new SinContextoDeEmpresa(
                    'No se puede cambiar la empresa de '.class_basename($model).'.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Consulta sin el filtro de empresa. Úsese solo donde el acceso ya está
     * autorizado de otra forma (p. ej. el postulante viendo sus postulaciones).
     *
     * @return Builder<static>
     */
    public static function sinFiltroDeEmpresa(): Builder
    {
        return static::query()->withoutGlobalScope(EmpresaScope::class);
    }
}
