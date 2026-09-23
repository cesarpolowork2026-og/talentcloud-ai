<?php

namespace App\Services\Registro;

use App\DTO\RegistroPostulanteData;
use App\Enums\Rol;
use App\Models\User;
use App\Repositories\Contracts\CandidatoRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registro de postulante: crea la cuenta con rol postulante y su perfil de
 * candidato con la fecha de aceptación del tratamiento de datos.
 */
class RegistrarPostulante
{
    public function __construct(private readonly CandidatoRepository $candidatos) {}

    public function ejecutar(RegistroPostulanteData $datos): User
    {
        if (! $datos->aceptaTratamientoDeDatos) {
            throw new InvalidArgumentException('El postulante debe aceptar el tratamiento de sus datos.');
        }

        $user = DB::transaction(function () use ($datos): User {
            $user = User::create([
                'name' => $datos->nombre,
                'email' => $datos->email,
                'password' => $datos->password,
            ]);

            $user->assignRole(Rol::Postulante->value);

            $this->candidatos->crear([
                'user_id' => $user->id,
                'nombre' => $datos->nombre,
                'email' => $datos->email,
                'consentimiento_at' => now(),
            ]);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
