<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Modalidad;
use App\Http\Controllers\Controller;
use App\Http\Resources\VacantePublicaResource;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Vacantes abiertas de empresas activas, solo lectura. Usa la misma lectura que la bolsa pública
 * (VacanteRepository::publicadas*), así que nunca expone borradores, vacantes cerradas ni datos internos.
 */
class VacantesController extends Controller
{
    private const POR_PAGINA_MAXIMO = 50;

    public function index(Request $request, VacanteRepository $vacantes): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'modalidad' => ['nullable', Rule::enum(Modalidad::class)],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:'.self::POR_PAGINA_MAXIMO],
        ]);

        $consulta = $vacantes->publicadasParaListado()->latest('vacantes.publicada_at')->orderByDesc('vacantes.id');

        if (filled($datos['modalidad'] ?? null)) {
            $consulta->where('vacantes.modalidad', $datos['modalidad']);
        }

        if (filled($datos['q'] ?? null)) {
            $patron = '%'.addcslashes($datos['q'], '%_\\').'%';
            $consulta->where(fn ($q) => $q
                ->whereRaw('unaccent(vacantes.titulo) ilike unaccent(?)', [$patron])
                ->orWhereRaw("unaccent(coalesce(vacantes.ubicacion, '')) ilike unaccent(?)", [$patron])
                ->orWhereHas('empresa', fn ($e) => $e->whereRaw('unaccent(nombre) ilike unaccent(?)', [$patron])));
        }

        return VacantePublicaResource::collection(
            $consulta->paginate((int) ($datos['por_pagina'] ?? 12))->withQueryString()
        );
    }

    public function show(int $id, VacanteRepository $vacantes): VacantePublicaResource|JsonResponse
    {
        $vacante = $vacantes->buscarPublicada($id);

        if ($vacante === null) {
            return response()->json(['mensaje' => 'Vacante no encontrada.'], 404);
        }

        return new VacantePublicaResource($vacante);
    }
}
