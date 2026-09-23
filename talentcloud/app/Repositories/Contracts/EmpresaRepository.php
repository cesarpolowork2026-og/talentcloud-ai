<?php

namespace App\Repositories\Contracts;

use App\Models\Empresa;

interface EmpresaRepository
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Empresa;

    public function buscar(int $id): ?Empresa;
}
