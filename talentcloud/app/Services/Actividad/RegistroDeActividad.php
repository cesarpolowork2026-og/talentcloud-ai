<?php

namespace App\Services\Actividad;

use App\Enums\TipoActividad;
use App\Models\Actividad;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Anota una línea en la actividad reciente de una empresa. Funciona también
 * sin contexto de empresa (Jobs de la cola "ia"): fija la empresa indicada.
 *
 * El sujeto de lo que afecta a un candidato es su postulación: así, al
 * eliminar su cuenta se borra también la actividad que lo nombra.
 */
class RegistroDeActividad
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function registrar(int $empresaId, TipoActividad $tipo, string $descripcion, ?Model $sujeto = null, ?User $actor = null): Actividad
    {
        return $this->tenant->ejecutarPara($empresaId, function () use ($empresaId, $tipo, $descripcion, $sujeto, $actor) {
            $actividad = new Actividad(['tipo' => $tipo->value, 'descripcion' => $descripcion, 'actor_id' => $actor?->id]);
            $actividad->empresa_id = $empresaId;

            if ($sujeto !== null) {
                $actividad->sujeto()->associate($sujeto);
            }

            $actividad->save();

            return $actividad;
        });
    }
}
