<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Cuenta\ExportarMisDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Descargar mis datos" del postulante: un JSON con lo que la plataforma
 * guarda de él (ver ExportarMisDatos). La ruta ya exige el rol postulante y
 * solo exporta los datos de quien la pide.
 */
class DescargarMisDatosController extends Controller
{
    public function __invoke(Request $request, ExportarMisDatos $exportar): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $exportar->de($user),
            200,
            [
                'Content-Disposition' => 'attachment; filename="mis-datos-talentcloud-'.now()->format('Y-m-d').'.json"',
                'Cache-Control' => 'no-store, private',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
