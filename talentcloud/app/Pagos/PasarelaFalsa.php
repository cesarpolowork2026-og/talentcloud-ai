<?php

namespace App\Pagos;

use App\DTO\AvisoDePago;
use App\DTO\SuscripcionData;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Models\Empresa;
use App\Models\User;

/**
 * Pasarela de los tests: no sale a internet. Guarda los pagos iniciados y
 * devuelve las suscripciones que el test le da con conSuscripcion(). Sus avisos
 * son JSON con la firma FIRMA_VALIDA.
 */
class PasarelaFalsa implements PasarelaDePago
{
    public const FIRMA_VALIDA = 'firma-de-prueba';

    /** @var list<array{cliente: string, empresa: int, plan: PlanEmpresa, periodicidad: Periodicidad, pagado: string, cancelado: string}> */
    public array $pagosIniciados = [];

    /** @var array<string, SuscripcionData> */
    private array $suscripciones = [];

    private int $clientes = 0;

    public function conSuscripcion(SuscripcionData $suscripcion): self
    {
        $this->suscripciones[$suscripcion->id] = $suscripcion;

        return $this;
    }

    public function crearCliente(Empresa $empresa, User $contacto): string
    {
        return 'cus_falso_'.(++$this->clientes);
    }

    public function urlDePago(string $clienteId, Empresa $empresa, PlanEmpresa $plan, Periodicidad $periodicidad, string $volverPagado, string $volverCancelado): string
    {
        $this->pagosIniciados[] = [
            'cliente' => $clienteId,
            'empresa' => $empresa->id,
            'plan' => $plan,
            'periodicidad' => $periodicidad,
            'pagado' => $volverPagado,
            'cancelado' => $volverCancelado,
        ];

        return 'https://pagos.example/checkout/'.count($this->pagosIniciados);
    }

    public function urlDelPortal(string $clienteId, string $volver): string
    {
        return 'https://pagos.example/portal/'.$clienteId;
    }

    public function suscripcion(string $id): ?SuscripcionData
    {
        return $this->suscripciones[$id] ?? null;
    }

    public function leerAviso(string $cuerpo, string $firma): AvisoDePago
    {
        $datos = json_decode($cuerpo, true);

        if ($firma !== self::FIRMA_VALIDA || ! is_array($datos) || ! is_string($datos['id'] ?? null) || ! is_string($datos['tipo'] ?? null)) {
            throw new AvisoNoValido('Aviso sin firma válida.');
        }

        return new AvisoDePago(
            $datos['id'],
            $datos['tipo'],
            is_string($datos['suscripcion'] ?? null) ? $datos['suscripcion'] : null,
            is_string($datos['cliente'] ?? null) ? $datos['cliente'] : null,
            ($datos['pago_fallido'] ?? false) === true,
        );
    }
}
