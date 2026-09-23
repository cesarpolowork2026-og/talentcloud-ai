<?php

namespace App\Tenancy;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "La empresa y sus usuarios perderán acceso a la plataforma de inmediato"
 * (confirmación de Suspender del prototipo): si la empresa del usuario está
 * suspendida, se cierra su sesión en la siguiente petición.
 */
class VerificarEmpresaActiva
{
    public const MENSAJE = 'El acceso de tu empresa a TalentCloud está suspendido. Contacta con el administrador de la plataforma.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->empresa !== null && ! $user->empresa->estaActiva()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', self::MENSAJE);
        }

        return $next($request);
    }
}
