<?php

namespace App\Tenancy;

use App\Enums\Rol;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el TenantContext de la petición a partir del usuario autenticado.
 * Va en el grupo "web", así que también cubre las peticiones de Livewire.
 */
class EstablecerContextoTenant
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->contexto->limpiar();

        $user = $request->user();

        if ($user instanceof User) {
            if ($user->tieneRol(Rol::Admin)) {
                $this->contexto->establecerGlobal();
            } elseif ($user->empresa_id !== null) {
                $this->contexto->establecer($user->empresa_id);
            }
            // Postulantes: sin contexto. Acceden a sus datos por relaciones explícitas.
        }

        return $next($request);
    }
}
