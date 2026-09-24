<?php

namespace App\Services\Equipo;

use App\DTO\InvitacionData;
use App\Enums\Rol;
use App\Events\InvitacionEnviada;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Notifications\InvitacionRecibida;
use App\Repositories\Contracts\EquipoRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Invita a una persona al equipo de una empresa. Genera un token de un solo
 * uso (en la base solo queda su hash), vigente 7 días, y lo envía por correo.
 * Una nueva invitación al mismo correo reemplaza a la pendiente.
 */
class InvitarUsuario
{
    public const DIAS_VIGENCIA = 7;

    public function __construct(private readonly EquipoRepository $equipo) {}

    public function ejecutar(Empresa $empresa, InvitacionData $datos, ?User $invitador): Invitacion
    {
        if (! in_array($datos->rol, Rol::internosDeEmpresa(), true)) {
            throw new InvitacionNoValida('Solo se puede invitar como reclutador o administrador de empresa.');
        }

        $email = mb_strtolower(trim($datos->email));

        if ($this->equipo->existeUsuarioConEmail($email)) {
            throw new InvitacionNoValida('Ya existe una cuenta con este correo.');
        }

        $token = Str::random(64);

        try {
            $invitacion = DB::transaction(function () use ($empresa, $datos, $email, $token, $invitador): Invitacion {
                $this->equipo->retirarInvitacionesPendientes($empresa, $email);

                $invitacion = $this->equipo->crearInvitacion($empresa, [
                    'nombre' => $datos->nombre,
                    'email' => $email,
                    'rol' => $datos->rol,
                    'token_hash' => hash('sha256', $token),
                    'expira_at' => now()->addDays(self::DIAS_VIGENCIA),
                ]);
                $invitacion->invitada_por = $invitador?->id;
                $invitacion->save();

                // Envío síncrono (el token en claro no se guarda en la cola) y dentro de la
                // transacción: si el correo no sale, no queda una invitación que nadie recibió.
                Notification::route('mail', $email)->notifyNow(
                    new InvitacionRecibida($empresa, $invitacion, $token, $invitador?->name),
                );

                return $invitacion;
            });
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw new InvitacionNoValida('No se pudo enviar el correo de invitación. Inténtalo de nuevo en unos minutos.');
        }

        InvitacionEnviada::dispatch($empresa, $invitacion, $invitador);

        return $invitacion;
    }
}
