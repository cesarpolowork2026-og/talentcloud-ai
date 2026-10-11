<?php

namespace App\Services\Pagos;

use App\DTO\AvisoDePago;
use App\Events\PagoFallido;
use App\Models\Empresa;
use App\Pagos\PasarelaDePago;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Atiende un aviso (webhook) ya verificado de la pasarela. El estado de la
 * suscripción no se toma del aviso sino que se relee de la pasarela: los avisos
 * pueden llegar repetidos o desordenados y el resultado es el mismo. El aviso de
 * cobro fallido se atiende una sola vez (eventos_de_pago).
 */
class AtenderAvisoDePago
{
    public function __construct(
        private readonly PasarelaDePago $pasarela,
        private readonly SincronizarSuscripcion $sincronizar,
        private readonly TenantContext $tenant,
    ) {}

    public function ejecutar(AvisoDePago $aviso): void
    {
        // Tarea de plataforma: los listeners del cambio de plan trabajan con cualquier empresa.
        $this->tenant->ejecutarGlobal(function () use ($aviso): void {
            if ($aviso->suscripcionId !== null && ($suscripcion = $this->pasarela->suscripcion($aviso->suscripcionId)) !== null) {
                $this->sincronizar->ejecutar($suscripcion);
            }

            if ($aviso->pagoFallido && $aviso->clienteId !== null) {
                $this->avisarPagoFallido($aviso);
            }
        });
    }

    /**
     * Solo si la empresa sigue pagando: el primer pago se resuelve en la página de la
     * pasarela, y tras el último reintento la suscripción ya terminó (y pasó a Gratis).
     */
    private function avisarPagoFallido(AvisoDePago $aviso): void
    {
        $empresa = Empresa::query()->where('pasarela_cliente_id', $aviso->clienteId)->first();

        if ($empresa === null || ! $empresa->tieneSuscripcionVigente()) {
            return;
        }

        $nuevo = DB::table('eventos_de_pago')->insertOrIgnore(['id' => $aviso->id, 'tipo' => $aviso->tipo, 'created_at' => now()]);

        if ($nuevo === 1) {
            PagoFallido::dispatch($empresa);
        }
    }
}
