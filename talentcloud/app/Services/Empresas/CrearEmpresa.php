<?php

namespace App\Services\Empresas;

use App\DTO\InvitacionData;
use App\DTO\NuevaEmpresaData;
use App\Enums\EstadoEmpresa;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\User;
use App\Repositories\Contracts\EmpresaRepository;
use App\Services\Equipo\InvitarUsuario;
use Illuminate\Support\Facades\DB;

/**
 * "Nueva empresa" del admin SaaS: crea la empresa y envía la invitación a su
 * administrador (rol empresa), que completa el onboarding al entrar.
 */
class CrearEmpresa
{
    public function __construct(
        private readonly EmpresaRepository $empresas,
        private readonly InvitarUsuario $invitar,
    ) {}

    public function ejecutar(NuevaEmpresaData $datos, User $admin): Empresa
    {
        // Si la invitación falla (p. ej. el correo ya tiene cuenta), no queda una empresa sin administrador.
        return DB::transaction(function () use ($datos, $admin): Empresa {
            $empresa = $this->empresas->crear([
                'nombre' => $datos->nombre,
                'plan' => $datos->plan,
                'estado' => EstadoEmpresa::Activa,
                'onboarding' => array_fill_keys(Empresa::PASOS_ONBOARDING, false),
            ]);

            $this->invitar->ejecutar($empresa, new InvitacionData(null, $datos->emailAdministrador, Rol::Empresa), $admin);

            return $empresa;
        });
    }
}
