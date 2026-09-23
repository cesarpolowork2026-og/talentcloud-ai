<?php

namespace App\Services\Empresas;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;

/**
 * Suspender / Activar del admin SaaS. Suspendida, sus usuarios pierden el
 * acceso de inmediato (VerificarAcceso); sus datos se conservan.
 */
class CambiarEstadoEmpresa
{
    public function __construct(private readonly EmpresaRepository $empresas) {}

    public function ejecutar(Empresa $empresa): Empresa
    {
        $nuevo = $empresa->estaActiva() ? EstadoEmpresa::Suspendida : EstadoEmpresa::Activa;

        return $this->empresas->actualizar($empresa, ['estado' => $nuevo]);
    }
}
