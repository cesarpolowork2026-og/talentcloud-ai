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
    public function __construct(
        private readonly PasarelaDePago $pasarela,
        private readonly SincronizarSuscripcion $sincronizar,
    ) {}

    public function ejecutar(Empresa $empresa, PlanEmpresa $plan, Periodicidad $periodicidad, User $quien): string
    {
        if (! $plan->sePagaEnLinea()) {
            throw new OperacionNoPermitida('El plan '.$plan->etiqueta().' no se paga en línea.', 'plan');
        }

        $empresa->refresh();

        if ($empresa->plan === PlanEmpresa::Enterprise) {
            // Pagar Starter o Pro bajaría un contrato a medida: el cambio se coordina con el equipo.
            throw new OperacionNoPermitida('Tu plan Enterprise se gestiona con el equipo de TalentCloud: solicita el cambio y te escribiremos.', 'plan');
        }

        $this->verificarSinSuscripcion($empresa);

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

    /**
     * Una empresa paga una sola suscripción. Se pregunta también a la pasarela: si acaba
     * de pagar y su aviso aún no llegó, volver a «Pagar» abriría una segunda suscripción.
     * Lo que responda se aplica ya, sin esperar al aviso.
     */
    private function verificarSinSuscripcion(Empresa $empresa): void
    {
        if (! $empresa->tieneSuscripcionVigente() && $empresa->pasarela_cliente_id !== null
            && ($vigente = $this->pasarela->suscripcionVigenteDe($empresa->pasarela_cliente_id)) !== null) {
            $this->sincronizar->ejecutar($vigente);
            $empresa->refresh();
        }

        if ($empresa->tieneSuscripcionVigente()) {
            throw new OperacionNoPermitida('Tu empresa ya paga el plan '.$empresa->plan->etiqueta().'. Para cambiarlo o cancelarlo, usa «Gestionar suscripción».', 'plan');
        }
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
