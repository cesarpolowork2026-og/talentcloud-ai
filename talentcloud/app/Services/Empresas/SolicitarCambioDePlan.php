<?php

namespace App\Services\Empresas;

use App\Enums\PlanEmpresa;
use App\Events\PlanSolicitado;
use App\Models\Empresa;
use App\Models\User;
use App\Pagos\CobroEnLinea;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/**
 * "Solicitar plan" de la cuenta de empresa: sin pasarela de pago, o para un plan
 * que se cotiza (Enterprise). La solicitud avisa al admin SaaS, que la aplica con
 * CambiarPlanEmpresa (y eso la da por atendida). Con pasarela, Starter y Pro se
 * pagan con ContratarPlan.
 */
class SolicitarCambioDePlan
{
    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, User $solicitante): Empresa
    {
        DB::transaction(function () use ($empresa, $plan): void {
            $fila = Empresa::query()->lockForUpdate()->findOrFail($empresa->id);

            // Con cobro en línea, Starter y Pro se pagan y una suscripción se cancela en su portal;
            // a mano solo se pide lo que se cotiza (Enterprise).
            if (CobroEnLinea::habilitado() && $plan->sePagaEnLinea()) {
                throw new OperacionNoPermitida('El plan '.$plan->etiqueta().' se contrata pagando en línea.', 'plan');
            }

            if ($fila->tieneSuscripcionVigente() && $plan === PlanEmpresa::Gratis) {
                throw new OperacionNoPermitida('Para pasar al plan Gratis, cancela tu suscripción en «Gestionar suscripción».', 'plan');
            }

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
