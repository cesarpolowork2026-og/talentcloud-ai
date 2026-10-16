<?php

namespace App\Services\Equipo;

use App\Models\User;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva o reactiva a un usuario interno. Desactivado pierde el acceso en su
 * siguiente petición y conserva su historial. Reactivar respeta el límite de
 * usuarios del plan.
 *
 * No se desactiva a quien tiene entrevistas por realizar: primero se cancelan o
 * se programan con otra persona. La acción es explícita (no un "alternar") y se
 * decide con la fila releída y bloqueada; si ya está en ese estado, no hace nada.
 */
class CambiarEstadoUsuario
{
    public function __construct(private readonly LimitesDelPlan $limites) {}

    public function desactivar(User $usuario): User
    {
        return DB::transaction(function () use ($usuario): User {
            $actual = $this->releer($usuario);

            if (! $actual->estaActivo()) {
                return $actual;
            }

            $this->verificarSinEntrevistasPorRealizar($actual);

            $actual->desactivado_at = now();
            $actual->save();

            return $actual;
        });
    }

    public function reactivar(User $usuario): User
    {
        return DB::transaction(function () use ($usuario): User {
            $actual = $this->releer($usuario);

            if ($actual->estaActivo()) {
                return $actual;
            }

            if ($actual->empresa_id !== null) {
                $this->limites->verificarUsuarioActivo($actual->empresa_id);
            }

            $actual->desactivado_at = null;
            $actual->save();

            return $actual;
        });
    }

    /** La fila bloqueada: ProgramarEntrevista bloquea la misma, así nadie le asigna una entrevista mientras tanto. */
    private function releer(User $usuario): User
    {
        return User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
    }

    private function verificarSinEntrevistasPorRealizar(User $usuario): void
    {
        $porRealizar = EntrevistasPorRealizar::consulta()->where('entrevistador_id', $usuario->id)->count();

        if ($porRealizar > 0) {
            // Sin género gramatical: no se conoce ni se deduce el de la persona.
            throw new OperacionNoPermitida(
                $usuario->name.' tiene '.EntrevistasPorRealizar::texto($porRealizar).' por realizar. '
                .EntrevistasPorRealizar::segun($porRealizar, 'Cancélala o prográmala', 'Cancélalas o prográmalas').' con otra persona en «Entrevistas» y vuelve a intentarlo.',
                'usuario',
            );
        }
    }
}
