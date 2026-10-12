<?php

namespace App\Pagos;

use App\DTO\AvisoDePago;
use App\DTO\SuscripcionData;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;

/**
 * Puerto de la pasarela de pago (Adapter): Stripe hoy (PasarelaStripe); en los
 * tests, PasarelaFalsa. Cambiar de pasarela (Culqi, Mercado Pago…) es escribir
 * otra implementación: los servicios de App\Services\Pagos no cambian.
 *
 * Los datos de la tarjeta nunca pasan por TalentCloud: la empresa paga en la
 * página de la pasarela y la pasarela confirma el resultado con un aviso firmado.
 */
interface PasarelaDePago
{
    /** Registra a la empresa como cliente de la pasarela y devuelve su id. */
    public function crearCliente(Empresa $empresa, User $contacto): string;

    /** URL de la página de pago de la pasarela para suscribirse a $plan. */
    public function urlDePago(string $clienteId, Empresa $empresa, PlanEmpresa $plan, Periodicidad $periodicidad, string $volverPagado, string $volverCancelado): string;

    /** URL del portal donde la empresa cambia su tarjeta o su plan, ve sus facturas o cancela. */
    public function urlDelPortal(string $clienteId, string $volver): string;

    /** Estado actual de la suscripción, leído de la pasarela (null si no existe). */
    public function suscripcion(string $id): ?SuscripcionData;

    /**
     * La suscripción vigente del cliente según la pasarela, aunque su aviso aún no
     * haya llegado (null si no tiene ninguna).
     */
    public function suscripcionVigenteDe(string $clienteId): ?SuscripcionData;

    /**
     * Lee un aviso (webhook) de la pasarela.
     *
     * @throws AvisoNoValido si la firma no corresponde al cuerpo
     */
    public function leerAviso(string $cuerpo, string $firma): AvisoDePago;
}
