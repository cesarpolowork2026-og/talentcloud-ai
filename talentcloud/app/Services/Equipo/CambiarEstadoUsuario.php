<?php

namespace App\Services\Equipo;

use App\Models\User;
use App\Services\Empresas\LimitesDelPlan;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva o reactiva a un usuario interno. Desactivado, pierde el acceso en
 * su siguiente petición (VerificarAcceso) y no puede iniciar sesión; su
 * historial (vacantes, entrevistas, evaluaciones) se conserva. Reactivar
 * respeta el límite de usuarios del plan.
 */
class CambiarEstadoUsuario
{
    public function __construct(private readonly LimitesDelPlan $limites) {}

    public function ejecutar(User $usuario): User
    {
        return DB::transaction(function () use ($usuario): User {
            if (! $usuario->estaActivo() && $usuario->empresa_id !== null) {
                $this->limites->verificarUsuarioActivo($usuario->empresa_id);
            }

            $usuario->desactivado_at = $usuario->estaActivo() ? now() : null;
            $usuario->save();

            return $usuario;
        });
    }
}
