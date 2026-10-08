<?php

namespace App\Services\Equipo;

use App\Enums\EstadoEntrevista;
use App\Models\Entrevista;
use App\Models\User;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva o reactiva a un usuario interno. Desactivado, pierde el acceso en
 * su siguiente petición (VerificarAcceso) y no puede iniciar sesión; su
 * historial (vacantes, entrevistas, evaluaciones) se conserva. Reactivar
 * respeta el límite de usuarios del plan.
 *
 * No se desactiva a quien tiene entrevistas por realizar: quedarían a nombre de
 * alguien sin acceso y el candidato esperaría a un entrevistador que no va a
 * llegar. Primero se cancelan o se programan con otra persona.
 */
class CambiarEstadoUsuario
{
    public function __construct(private readonly LimitesDelPlan $limites) {}

    public function ejecutar(User $usuario): User
    {
        return DB::transaction(function () use ($usuario): User {
            // Bloquea al usuario: ProgramarEntrevista también lo bloquea, así nadie le asigna una entrevista mientras tanto.
            User::whereKey($usuario->id)->lockForUpdate()->first();

            if ($usuario->estaActivo()) {
                $this->verificarSinEntrevistasPorRealizar($usuario);
            } elseif ($usuario->empresa_id !== null) {
                $this->limites->verificarUsuarioActivo($usuario->empresa_id);
            }

            $usuario->desactivado_at = $usuario->estaActivo() ? now() : null;
            $usuario->save();

            return $usuario;
        });
    }

    private function verificarSinEntrevistasPorRealizar(User $usuario): void
    {
        $porRealizar = Entrevista::sinFiltroDeEmpresa()
            ->where('entrevistador_id', $usuario->id)
            ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
            ->count();

        if ($porRealizar > 0) {
            throw new OperacionNoPermitida(
                $usuario->name.' tiene '.($porRealizar === 1 ? '1 entrevista' : "{$porRealizar} entrevistas").' por realizar. '
                .'En «Entrevistas», cancélalas o prográmalas con otra persona antes de desactivarlo.',
                'usuario',
            );
        }
    }
}
