<?php

namespace App\Tenancy;

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
        $user = $request->user();

        // Postulantes: sin contexto. Acceden a sus datos por relaciones explícitas.
        $this->contexto->establecerParaUsuario($user instanceof User ? $user : null);

        return $next($request);
    }
}
