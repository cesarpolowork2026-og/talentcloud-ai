<?php

namespace App\Services\Pagos;

use App\Models\Empresa;
use App\Pagos\PasarelaDePago;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * «Gestionar suscripción» de Mi empresa: el portal de la pasarela, donde la
 * empresa cambia su tarjeta o su plan, descarga sus facturas o cancela. Lo que
 * cambie allí llega de vuelta como aviso (AtenderAvisoDePago).
 */
class GestionarSuscripcion
{
    public function __construct(private readonly PasarelaDePago $pasarela) {}

    public function url(Empresa $empresa): string
    {
        if ($empresa->pasarela_cliente_id === null || $empresa->pasarela_suscripcion_id === null) {
            throw new OperacionNoPermitida('Tu empresa todavía no ha pagado un plan en línea.', 'plan');
        }

        return $this->pasarela->urlDelPortal($empresa->pasarela_cliente_id, route('mi-empresa'));
    }
}
