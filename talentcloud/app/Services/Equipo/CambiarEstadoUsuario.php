<?php

namespace App\Services\Equipo;

use App\Models\User;

/**
 * Desactiva o reactiva a un usuario interno. Desactivado, pierde el acceso en
 * su siguiente petición (VerificarAcceso) y no puede iniciar sesión; su
 * historial (vacantes, entrevistas, evaluaciones) se conserva.
 */
class CambiarEstadoUsuario
{
    public function ejecutar(User $usuario): User
    {
        $usuario->desactivado_at = $usuario->estaActivo() ? now() : null;
        $usuario->save();

        return $usuario;
    }
}
