<?php

namespace App\Services\Registro;

use App\DTO\RegistroEmpresaData;
use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use App\Repositories\Contracts\EmpresaRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * "Crear empresa": da de alta la empresa (plan Starter, activa, onboarding
 * pendiente) y su primera cuenta con rol empresa.
 */
class RegistrarEmpresa
{
    public function __construct(private readonly EmpresaRepository $empresas) {}

    public function ejecutar(RegistroEmpresaData $datos): User
    {
        $user = DB::transaction(function () use ($datos): User {
            $empresa = $this->empresas->crear([
                'nombre' => $datos->nombreEmpresa,
                'plan' => PlanEmpresa::Starter,
                'estado' => EstadoEmpresa::Activa,
                'onboarding' => array_fill_keys(Empresa::PASOS_ONBOARDING, false),
            ]);

            $user = new User([
                'name' => $datos->nombre,
                'email' => $datos->email,
                'password' => $datos->password,
            ]);
            $user->empresa_id = $empresa->id;
            $user->save();

            $user->assignRole(Rol::Empresa->value);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
