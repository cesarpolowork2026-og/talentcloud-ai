<?php

namespace App\Services\Empresas;

use App\Enums\PlanEmpresa;
use App\Events\PlanSolicitado;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/**
 * "Solicitar plan" de la cuenta de empresa. Sin pasarela de pago todavía, la
 * solicitud avisa al admin SaaS, que la aplica con CambiarPlanEmpresa (y eso la
 * da por atendida). Cuando haya pasarela, este es el punto que pasa a cobrar.
 */
class SolicitarCambioDePlan
{
    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, User $solicitante): Empresa
    {
        DB::transaction(function () use ($empresa, $plan): void {
            $fila = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);

            if ($fila->plan === $plan && ! $fila->enPrueba()) {
                throw new OperacionNoPermitida('Tu empresa ya tiene el plan '.$plan->etiqueta().'.', 'plan');
            }

            if ($fila->plan === $plan) {
                // Durante la prueba, el contratado es Gratis: pedirlo no cambia nada.
                throw new OperacionNoPermitida('Al terminar la prueba tu empresa pasará al plan '.$plan->etiqueta().' sin hacer nada.', 'plan');
            }

            if ($fila->plan_solicitado === $plan) {
                throw new OperacionNoPermitida('Ya solicitaste el plan '.$plan->etiqueta().' el '.Formato::fechaCorta($fila->plan_solicitado_at).'. El equipo de TalentCloud te escribirá para aplicarlo.', 'plan');
            }

            $fila->plan_solicitado = $plan;
            $fila->plan_solicitado_at = now();
            $fila->save();
        });

        $empresa->refresh();

        PlanSolicitado::dispatch($empresa, $plan, $solicitante);

        return $empresa;
    }
}
