<?php

namespace App\Services\Plataforma;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deja constancia de lo que hace el administrador SaaS sobre las empresas, los usuarios y el catálogo. Cada acción
 * guarda quién la hizo, qué hizo y, si aplica, sobre qué empresa.
 */
class RegistroDeAuditoria
{
    public const EMPRESA_CREADA = 'empresa.creada';

    public const EMPRESA_SUSPENDIDA = 'empresa.suspendida';

    public const EMPRESA_ACTIVADA = 'empresa.activada';

    public const PLAN_CAMBIADO = 'empresa.plan';

    public const USUARIO_DESACTIVADO = 'usuario.desactivado';

    public const USUARIO_REACTIVADO = 'usuario.reactivado';

    public const COMPETENCIA_CREADA = 'competencia.creada';

    public const COMPETENCIA_EDITADA = 'competencia.editada';

    public const COMPETENCIA_FUSIONADA = 'competencia.fusionada';

    public const COMPETENCIA_ELIMINADA = 'competencia.eliminada';

    public function anotar(User $actor, string $accion, string $descripcion, ?int $empresaId = null): Auditoria
    {
        return Auditoria::create([
            'usuario_id' => $actor->id,
            'usuario_nombre' => mb_substr($actor->name, 0, 120),
            'accion' => $accion,
            'descripcion' => $descripcion,
            'empresa_id' => $empresaId,
        ]);
    }

    /**
     * Las últimas acciones, de la más reciente a la más antigua.
     *
     * @return Collection<int, Auditoria>
     */
    public function ultimas(int $cuantas = 8): Collection
    {
        return Auditoria::query()->latest('created_at')->latest('id')->limit($cuantas)->get();
    }
}
