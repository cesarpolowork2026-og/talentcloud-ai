<?php

namespace App\Services\Empresas;

use App\Enums\PlanEmpresa;
use App\Events\PlanEmpresaCambiado;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Cambiar plan" del admin SaaS. Una bajada no toca lo existente: la empresa
 * conserva sus vacantes y usuarios aunque pase del nuevo límite y solo deja de
 * poder sumar más (LimitesDelPlan). Cambiar el plan termina la prueba y atiende
 * la solicitud, si la había. Si la empresa paga en línea, el plan lo cambia la
 * pasarela.
 *
 * Un plan de pago asignado a mano puede llevar un vencimiento (`$venceAt`): al
 * pasar, rige el plan Gratis sin tocar nada (Empresa::planVigente()). Sin fecha
 * no vence. Con el mismo plan y otra fecha (o tras vencer), el cambio es una
 * renovación.
 */
class CambiarPlanEmpresa
{
    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, ?User $actor, ?Carbon $venceAt = null): Empresa
    {
        $this->validarVencimiento($plan, $venceAt);

        $anterior = DB::transaction(function () use ($empresa, $plan, $venceAt): PlanEmpresa {
            // Bloquea la fila: el cambio no se cruza con una verificación de límites en curso.
            $fila = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);
            $anterior = $fila->plan;

            if ($anterior === $plan && $this->mismoVencimiento($fila->plan_vence_at, $venceAt)) {
                throw new OperacionNoPermitida($empresa->nombre.' ya tiene el plan '.$plan->etiqueta().($venceAt === null ? '' : ' con ese vencimiento').'.', 'plan');
            }

            if ($fila->tieneSuscripcionVigente()) {
                // El plan pagado en línea lo cambia la pasarela: el admin no contradice lo que se cobra.
                throw new OperacionNoPermitida($empresa->nombre.' paga el plan '.$anterior->etiqueta().' con tarjeta: lo cambia o lo cancela la empresa desde «Gestionar suscripción» en Mi empresa.', 'plan');
            }

            $fila->contratar($plan, $venceAt);
            $fila->save();

            return $anterior;
        });

        $empresa->refresh();

        PlanEmpresaCambiado::dispatch($empresa, $anterior, $actor);

        return $empresa;
    }

    /** Solo un plan de pago puede vencer, y siempre a futuro: una fecha pasada lo dejaría en Gratis de inmediato. */
    private function validarVencimiento(PlanEmpresa $plan, ?Carbon $venceAt): void
    {
        if ($venceAt === null) {
            return;
        }

        if ($plan === PlanEmpresa::Gratis) {
            throw new OperacionNoPermitida('El plan Gratis no vence.', 'planVenceEl');
        }

        if ($venceAt->isPast()) {
            throw new OperacionNoPermitida('El vencimiento debe ser una fecha futura.', 'planVenceEl');
        }
    }

    private function mismoVencimiento(?Carbon $actual, ?Carbon $nuevo): bool
    {
        return $actual === null || $nuevo === null ? $actual === $nuevo : $actual->equalTo($nuevo);
    }
}
