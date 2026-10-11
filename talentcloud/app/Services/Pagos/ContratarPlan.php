<?php

namespace App\Services\Pagos;

use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Pagos\PasarelaDePago;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * «Contratar» un plan de pago en «Mi empresa»: devuelve la página de pago de la
 * pasarela. Aquí no cambia nada del plan: lo cambia el aviso de la pasarela
 * cuando confirma el pago (AtenderAvisoDePago), así un pago abandonado o
 * rechazado nunca da un plan. Pagar durante la prueba la termina ese día.
 */
class ContratarPlan
{
    public function __construct(private readonly PasarelaDePago $pasarela) {}

    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, Periodicidad $periodicidad, User $quien): string
    {
        if (! $plan->sePagaEnLinea()) {
            throw new OperacionNoPermitida('El plan '.$plan->etiqueta().' no se paga en línea.', 'plan');
        }

        $empresa->refresh();

        if ($empresa->tieneSuscripcionVigente()) {
            throw new OperacionNoPermitida('Tu empresa ya paga el plan '.$empresa->plan->etiqueta().'. Para cambiarlo o cancelarlo, usa «Gestionar suscripción».', 'plan');
        }

        $cliente = $empresa->pasarela_cliente_id ?? $this->registrarCliente($empresa, $quien);

        return $this->pasarela->urlDePago(
            $cliente,
            $empresa,
            $plan,
            $periodicidad,
            route('mi-empresa', ['pago' => 'confirmando']),
            route('mi-empresa', ['pago' => 'cancelado']),
        );
    }

    /** Una sola vez por empresa: si dos pestañas lo crean a la vez, se queda el primero guardado. */
    private function registrarCliente(Empresa $empresa, User $quien): string
    {
        $cliente = $this->pasarela->crearCliente($empresa, $quien);

        $guardado = Empresa::query()
            ->whereKey($empresa->id)
            ->whereNull('pasarela_cliente_id')
            ->update(['pasarela_cliente_id' => $cliente]);

        return $guardado === 1 ? $cliente : (string) $empresa->refresh()->pasarela_cliente_id;
    }
}
