<?php

namespace App\DTO;

use App\Enums\EstadoSuscripcion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use Illuminate\Support\Carbon;

/** Estado actual de una suscripción, leído de la pasarela de pago. */
final readonly class SuscripcionData
{
    public function __construct(
        public string $id,
        public string $clienteId,
        public PlanEmpresa $plan,
        public Periodicidad $periodicidad,
        public EstadoSuscripcion $estado,
        /** Fin del periodo pagado: se renueva (o termina, si se canceló) ese día. */
        public ?Carbon $renuevaAt,
        public bool $cancelaAlFinal,
    ) {}
}
