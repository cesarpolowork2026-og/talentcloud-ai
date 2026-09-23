<?php

namespace App\Repositories\Eloquent;

use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Repositories\Contracts\EquipoRepository;
use App\Tenancy\EmpresaScope;
use Illuminate\Support\Collection;

class EloquentEquipoRepository implements EquipoRepository
{
    public function usuariosDe(Empresa $empresa): Collection
    {
        return User::query()
            ->deEmpresa($empresa->id)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function invitacionesPendientesDe(Empresa $empresa): Collection
    {
        return Invitacion::query()
            ->withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $empresa->id)
            ->whereNull('aceptada_at')
            ->where('expira_at', '>', now())
            ->orderBy('created_at')
            ->get();
    }

    public function crearInvitacion(Empresa $empresa, array $datos): Invitacion
    {
        $invitacion = new Invitacion($datos);
        $invitacion->empresa_id = $empresa->id;
        $invitacion->save();

        return $invitacion;
    }

    public function retirarInvitacionesPendientes(Empresa $empresa, string $email): void
    {
        Invitacion::query()
            ->withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $empresa->id)
            ->where('email', $email)
            ->whereNull('aceptada_at')
            ->delete();
    }

    public function invitacionVigentePorToken(string $token): ?Invitacion
    {
        return Invitacion::query()
            ->withoutGlobalScope(EmpresaScope::class)
            ->with('empresa')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('aceptada_at')
            ->where('expira_at', '>', now())
            ->first();
    }

    public function existeUsuarioConEmail(string $email): bool
    {
        return User::where('email', mb_strtolower($email))->exists();
    }
}
