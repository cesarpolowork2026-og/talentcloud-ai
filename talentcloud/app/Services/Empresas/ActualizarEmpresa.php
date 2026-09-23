<?php

namespace App\Services\Empresas;

use App\DTO\DatosEmpresaData;
use App\Events\PerfilEmpresaActualizado;
use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;

/** Guarda el perfil de la empresa ("Completa el perfil de tu empresa"). */
class ActualizarEmpresa
{
    public function __construct(private readonly EmpresaRepository $empresas) {}

    public function ejecutar(Empresa $empresa, DatosEmpresaData $datos): Empresa
    {
        $empresa = $this->empresas->actualizar($empresa, [
            'nombre' => $datos->nombre,
            'sector' => $datos->sector,
            'sitio_web' => $datos->sitioWeb,
            'descripcion' => $datos->descripcion,
        ]);

        PerfilEmpresaActualizado::dispatch($empresa);

        return $empresa;
    }
}
