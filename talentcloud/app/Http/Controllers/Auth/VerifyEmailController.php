<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Navegacion;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cada rol vuelve a su pantalla de inicio (el postulante no tiene dashboard).
        $destino = Navegacion::rutaInicio($user).'?verified=1';

        // El aviso confirma la verificación: sin él, la persona llega a su inicio sin saber si funcionó.
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($destino)->with('correo_verificado', true);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($destino)->with('correo_verificado', true);
    }
}
