<?php

namespace App\Tenancy;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión, en la siguiente petición, de:
 * - los usuarios de una empresa suspendida ("La empresa y sus usuarios
 *   perderán acceso a la plataforma de inmediato", confirmación del prototipo);
 * - los usuarios internos que su empresa desactivó.
 */
class VerificarAcceso
{
    public const EMPRESA_SUSPENDIDA = 'El acceso de tu empresa a TalentCloud está suspendido. Contacta con el administrador de la plataforma.';

    public const USUARIO_DESACTIVADO = 'Tu cuenta fue desactivada. Contacta con el administrador de tu empresa.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($motivo = self::motivoDeBloqueo($user)) !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $motivo);
        }

        return $next($request);
    }

    /** Mensaje de por qué la cuenta no puede usar la plataforma, o null si puede. */
    public static function motivoDeBloqueo(User $user): ?string
    {
        if ($user->desactivado_at !== null) {
            return self::USUARIO_DESACTIVADO;
        }

        if ($user->empresa !== null && ! $user->empresa->estaActiva()) {
            return self::EMPRESA_SUSPENDIDA;
        }

        return null;
    }
}
