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
 * prueba y atiende la solicitud de la empresa, si la había. Si la empresa paga
 * en línea, su plan lo cambia la pasarela: el admin aplica los planes que no se
 * pagan con tarjeta (Enterprise, cortesía o sin pasarela configurada).
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

            if ($fila->tieneSuscripcionVigente()) {
                // El plan pagado en línea lo cambia la pasarela: el admin no contradice lo que se cobra.
                throw new OperacionNoPermitida($empresa->nombre.' paga el plan '.$anterior->etiqueta().' con tarjeta: lo cambia o lo cancela la empresa desde «Gestionar suscripción» en Mi empresa.', 'plan');
            }

            $fila->contratar($plan);
            $fila->save();

            return $anterior;
        });

        $empresa->refresh();

        PlanEmpresaCambiado::dispatch($empresa, $anterior, $actor);

        return $empresa;
    }
}
