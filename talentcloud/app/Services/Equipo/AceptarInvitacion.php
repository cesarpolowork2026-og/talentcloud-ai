<?php

namespace App\Services\Equipo;

use App\Models\User;
use App\Repositories\Contracts\EquipoRepository;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * La persona invitada crea su cuenta: queda en la empresa con el rol de la
 * invitación y con el correo verificado (lo demostró al abrir el enlace).
 */
class AceptarInvitacion
{
    public function __construct(
        private readonly EquipoRepository $equipo,
        private readonly LimitesDelPlan $limites,
    ) {}

    public function ejecutar(string $token, string $nombre, string $password): User
    {
        return DB::transaction(function () use ($token, $nombre, $password): User {
            $invitacion = $this->equipo->invitacionVigentePorToken($token);

            if ($invitacion === null) {
                throw new InvitacionNoValida('La invitación no existe, ya se usó o venció.');
            }

            if ($this->equipo->existeUsuarioConEmail($invitacion->email)) {
                throw new InvitacionNoValida('Ya existe una cuenta con este correo.');
            }

            // La invitación ya ocupaba lugar, pero el plan pudo bajar desde que se envió.
            try {
                $this->limites->verificarUsuarioActivo($invitacion->empresa_id);
            } catch (OperacionNoPermitida) {
                throw new EquipoCompleto($invitacion->empresa?->nombre.' alcanzó el límite de usuarios de su plan. Pide a quien te invitó que libere un lugar y vuelve a intentarlo: tu invitación sigue vigente.');
            }

            $user = new User([
                'name' => $nombre,
                'email' => $invitacion->email,
                'password' => $password,
            ]);
            $user->empresa_id = $invitacion->empresa_id;
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole($invitacion->rol->value);

            $invitacion->aceptada_at = now();
            $invitacion->save();

            return $user;
        });
    }
}
