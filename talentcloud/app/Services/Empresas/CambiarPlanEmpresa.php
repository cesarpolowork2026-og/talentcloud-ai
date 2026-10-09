<?php

namespace App\Services\Empresas;

use App\Enums\PlanEmpresa;
use App\Events\PlanEmpresaCambiado;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * "Cambiar plan" del admin SaaS. Una bajada de plan no toca lo existente: la
 * empresa conserva sus vacantes y usuarios aunque superen el nuevo límite, y
 * solo deja de poder sumar más (LimitesDelPlan). Cambiar el plan termina la
 * prueba y atiende la solicitud de la empresa, si la había.
 */
class CambiarPlanEmpresa
{
    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, ?User $actor): Empresa
    {
        $anterior = DB::transaction(function () use ($empresa, $plan): PlanEmpresa {
            // Bloquea la fila: el cambio no se cruza con una verificación de límites en curso.
            $fila = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);
            $anterior = $fila->plan;

            if ($anterior === $plan) {
                throw new OperacionNoPermitida($empresa->nombre.' ya tiene el plan '.$plan->etiqueta().'.', 'plan');
            }

            $fila->plan = $plan;
            $fila->prueba_hasta = null;
            $fila->plan_solicitado = null;
            $fila->plan_solicitado_at = null;
            $fila->save();

            return $anterior;
        });

        $empresa->refresh();

        PlanEmpresaCambiado::dispatch($empresa, $anterior, $actor);

        return $empresa;
    }
}
