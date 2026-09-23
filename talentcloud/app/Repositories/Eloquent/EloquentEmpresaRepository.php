<?php

namespace App\Repositories\Eloquent;

use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;

class EloquentEmpresaRepository implements EmpresaRepository
{
    public function crear(array $datos): Empresa
    {
        return Empresa::create($datos);
    }

    public function buscar(int $id): ?Empresa
    {
        return Empresa::find($id);
    }
}
