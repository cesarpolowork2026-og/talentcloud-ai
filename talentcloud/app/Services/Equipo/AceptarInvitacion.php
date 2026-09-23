<?php

namespace App\Services\Equipo;

use App\Models\User;
use App\Repositories\Contracts\EquipoRepository;
use Illuminate\Support\Facades\DB;

/**
 * La persona invitada crea su cuenta: queda en la empresa con el rol de la
 * invitación y con el correo verificado (lo demostró al abrir el enlace).
 */
class AceptarInvitacion
{
    public function __construct(private readonly EquipoRepository $equipo) {}

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
