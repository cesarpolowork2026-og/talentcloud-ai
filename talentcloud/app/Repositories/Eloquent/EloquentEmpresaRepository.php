<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;
use App\Tenancy\EmpresaScope;
use Illuminate\Database\Eloquent\Builder;

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

    public function actualizar(Empresa $empresa, array $datos): Empresa
    {
        $empresa->update($datos);

        return $empresa;
    }

    public function marcarPasoOnboarding(Empresa $empresa, string $paso): Empresa
    {
        Empresa::whereKey($empresa->id)
            ->whereNull('onboarding')
            ->update(['onboarding' => json_encode(array_fill_keys(Empresa::PASOS_ONBOARDING, false))]);

        // jsonb_set sobre la clave: no pisa los pasos que otra petición haya marcado.
        Empresa::whereKey($empresa->id)->update(['onboarding->'.$paso => true]);

        return $empresa->refresh();
    }

    public function consultaParaAdministracion(): Builder
    {
        return Empresa::query()->withCount($this->conteos());
    }

    public function conConteos(Empresa $empresa): Empresa
    {
        return $empresa->loadCount($this->conteos());
    }

    /**
     * Los conteos de vacantes ignoran el filtro por empresa: ya van acotados a
     * cada empresa por la relación y así funcionan para el admin y para la propia empresa.
     *
     * @return array<string|int, mixed>
     */
    private function conteos(): array
    {
        return [
            'usuarios',
            'vacantes as vacantes_activas_count' => fn (Builder $q) => $q
                ->withoutGlobalScope(EmpresaScope::class)
                ->whereIn('estado', EstadoVacante::activos()),
        ];
    }
}
