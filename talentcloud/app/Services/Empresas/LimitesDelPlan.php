<?php

namespace App\Services\Empresas;

use App\DTO\UsoDelPlan;
use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Límites de uso del plan vigente (Empresa::planVigente()). Solo impiden crear
 * más, nunca bloquean lo existente. Las verificaciones se llaman dentro de la
 * transacción que crea lo nuevo: bloquean la fila de la empresa para que dos
 * peticiones simultáneas no ocupen a la vez el último lugar.
 */
class LimitesDelPlan
{
    public function uso(Empresa $empresa): UsoDelPlan
    {
        return new UsoDelPlan(
            plan: $empresa->planVigente(),
            vacantesActivas: $this->vacantesActivas($empresa->id),
            usuariosActivos: $this->usuariosActivos($empresa->id),
            invitacionesPendientes: $this->invitacionesPendientes($empresa->id),
            pruebaHasta: $empresa->enPrueba() ? $empresa->prueba_hasta : null,
        );
    }

    /** Antes de publicar una vacante (Borrador a Abierta). */
    public function verificarVacanteActiva(int $empresaId): void
    {
        $empresa = $this->bloquear($empresaId);
        $limite = $empresa->planVigente()->limiteVacantesActivas();

        if ($limite !== null && $this->vacantesActivas($empresa->id) >= $limite) {
            throw new OperacionNoPermitida(
                'El plan '.$empresa->etiquetaDelPlan().' permite '.($limite === 1 ? '1 vacante activa' : $limite.' vacantes activas').' a la vez. Cierra una vacante o cambia de plan en Mi empresa.',
                'estado',
            );
        }
    }

    /**
     * Antes de invitar: cuentan los usuarios activos y las invitaciones
     * pendientes (salvo la del mismo correo, que la nueva reemplaza).
     */
    public function verificarInvitacion(int $empresaId, string $email): void
    {
        $empresa = $this->bloquear($empresaId);
        $limite = $empresa->planVigente()->limiteUsuarios();

        if ($limite === null) {
            return;
        }

        $ocupados = $this->usuariosActivos($empresa->id) + $this->invitacionesPendientes($empresa->id, salvoEmail: $email);

        if ($ocupados >= $limite) {
            throw new OperacionNoPermitida(
                'El plan '.$empresa->etiquetaDelPlan().' permite '.$limite.' usuarios, contando las invitaciones pendientes. Revoca una invitación, desactiva un usuario o cambia de plan en Mi empresa.',
                'invitadoEmail',
            );
        }
    }

    /** Antes de que alguien entre al equipo (aceptar una invitación o reactivar a un usuario). */
    public function verificarUsuarioActivo(int $empresaId): void
    {
        $empresa = $this->bloquear($empresaId);
        $limite = $empresa->planVigente()->limiteUsuarios();

        if ($limite !== null && $this->usuariosActivos($empresa->id) >= $limite) {
            throw new OperacionNoPermitida(
                'El plan '.$empresa->etiquetaDelPlan().' de '.$empresa->nombre.' permite '.$limite.' usuarios activos y ya están en uso. Desactiva a otro usuario o cambia de plan en Mi empresa.',
                'usuario',
            );
        }
    }

    private function bloquear(int $empresaId): Empresa
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('LimitesDelPlan debe verificarse dentro de una transacción.');
        }

        return Empresa::query()->lockForUpdate()->findOrFail($empresaId);
    }

    private function vacantesActivas(int $empresaId): int
    {
        return Vacante::sinFiltroDeEmpresa()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', EstadoVacante::activos())
            ->count();
    }

    private function usuariosActivos(int $empresaId): int
    {
        return User::query()->deEmpresa($empresaId)->whereNull('desactivado_at')->count();
    }

    private function invitacionesPendientes(int $empresaId, ?string $salvoEmail = null): int
    {
        return Invitacion::sinFiltroDeEmpresa()
            ->where('empresa_id', $empresaId)
            ->whereNull('aceptada_at')
            ->where('expira_at', '>', now())
            ->when($salvoEmail !== null, fn ($q) => $q->where('email', '!=', mb_strtolower(trim($salvoEmail))))
            ->count();
    }
}
