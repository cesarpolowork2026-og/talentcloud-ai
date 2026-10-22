<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** GET /api/v1/salud: la API responde y llega a la base de datos (503 si no). */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return response()->json(['estado' => 'degradado', 'base_de_datos' => false], 503);
        }

        return response()->json(['estado' => 'ok', 'base_de_datos' => true, 'version' => 'v1']);
    }
}
