<?php

namespace App\Services\Empresas;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;
use Illuminate\Support\Facades\DB;

/**
 * Suspender / Activar del admin SaaS. Suspendida, sus usuarios pierden el acceso
 * de inmediato (VerificarAcceso) y sus datos se conservan. La acción es
 * explícita (no un "alternar") y se decide con la fila releída y bloqueada; si
 * ya está en ese estado, no hace nada.
 */
class CambiarEstadoEmpresa
{
    public function __construct(private readonly EmpresaRepository $empresas) {}

    public function suspender(Empresa $empresa): Empresa
    {
        return $this->llevarA($empresa, EstadoEmpresa::Suspendida);
    }

    public function activar(Empresa $empresa): Empresa
    {
        return $this->llevarA($empresa, EstadoEmpresa::Activa);
    }

    private function llevarA(Empresa $empresa, EstadoEmpresa $estado): Empresa
    {
        return DB::transaction(function () use ($empresa, $estado): Empresa {
            $actual = Empresa::whereKey($empresa->id)->lockForUpdate()->firstOrFail();

            return $actual->estado === $estado ? $actual : $this->empresas->actualizar($actual, ['estado' => $estado]);
        });
    }
}
