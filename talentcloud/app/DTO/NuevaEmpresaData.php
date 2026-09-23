<?php

namespace App\DTO;

use App\Enums\PlanEmpresa;

/** Datos del formulario "Nueva empresa" del admin SaaS. */
final readonly class NuevaEmpresaData
{
    public function __construct(
        public string $nombre,
        public PlanEmpresa $plan,
        public string $emailAdministrador,
    ) {}
}
