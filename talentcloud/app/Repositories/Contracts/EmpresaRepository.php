<?php

namespace App\Repositories\Contracts;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;

interface EmpresaRepository
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Empresa;

    public function buscar(int $id): ?Empresa;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Empresa $empresa, array $datos): Empresa;

    /**
     * Consulta base de la pantalla "Empresas" del admin, con los conteos
     * usuarios_count y vacantes_activas_count.
     *
     * @return Builder<Empresa>
     */
    public function consultaParaAdministracion(): Builder;

    /**
     * Carga los conteos de usuarios y vacantes activas en una empresa.
     */
    public function conConteos(Empresa $empresa): Empresa;
}
