<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Postular" desde la bolsa pública. Sin sesión, guarda esta misma ruta como
 * destino y lleva a iniciar sesión (o a registrarse con ?cuenta=nueva): al
 * volver, la ruta se resuelve según el rol. El postulante llega a la vacante
 * en su panel, donde se aplican todas las reglas de PostularmeAVacante; otro
 * rol vuelve a la vacante pública con un aviso (no puede postular).
 */
class PostularDesdeEmpleosController extends Controller
{
    public function __invoke(Request $request, int $id, VacanteRepository $vacantes): RedirectResponse
    {
        abort_if($vacantes->buscarPublicada($id) === null, 404);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            // Solo esta ruta interna como destino: nunca una URL que venga en la petición.
            $request->session()->put('url.intended', route('empleos.postular', $id));

            return redirect()->route($request->query('cuenta') === 'nueva' ? 'register.postulante' : 'login');
        }

        if ($user->tieneRol(Rol::Postulante)) {
            return redirect()->route('postulante.vacantes.show', $id);
        }

        return redirect()->route('empleos.show', $id)
            ->with('aviso', 'Para postular, inicia sesión con una cuenta de postulante.');
    }
}
