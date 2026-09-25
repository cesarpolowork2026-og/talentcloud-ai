<?php

namespace App\Services\Vacantes;

use App\Enums\EstadoVacante;
use App\Events\VacanteCerrada;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Empresas\LimitesDelPlan;
use Illuminate\Support\Facades\DB;

/**
 * Transiciones de estado de una vacante: Borrador → Abierta ⇄ En revisión → Cerrada.
 * Publicar un borrador es lo único que suma una vacante activa: ahí se aplica
 * el límite del plan (Abierta ⇄ En revisión no cambia el número y una cerrada
 * no se reabre).
 */
class CambiarEstadoVacante
{
    public function __construct(
        private readonly VacanteRepository $vacantes,
        private readonly LimitesDelPlan $limites,
    ) {}

    public function ejecutar(Vacante $vacante, EstadoVacante $destino): Vacante
    {
        if (! $vacante->estado->puedePasarA($destino)) {
            throw new OperacionNoPermitida(
                'Una vacante '.mb_strtolower($vacante->estado->etiqueta()).' no puede pasar a '.mb_strtolower($destino->etiqueta()).'.'
            );
        }

        if ($destino === EstadoVacante::Abierta && ! Requisito::where('vacante_id', $vacante->id)->exists()) {
            throw new OperacionNoPermitida('Agrega al menos un requisito antes de publicar la vacante.');
        }

        $datos = ['estado' => $destino];

        if ($destino === EstadoVacante::Abierta && $vacante->publicada_at === null) {
            $datos['publicada_at'] = now();
        }

        if ($destino === EstadoVacante::Cerrada) {
            $datos['cerrada_at'] = now();
        }

        $publicaBorrador = $vacante->estado === EstadoVacante::Borrador && $destino === EstadoVacante::Abierta;

        $vacante = DB::transaction(function () use ($vacante, $datos, $publicaBorrador): Vacante {
            if ($publicaBorrador) {
                $this->limites->verificarVacanteActiva($vacante->empresa_id);
            }

            return $this->vacantes->actualizar($vacante, $datos);
        });

        if ($destino === EstadoVacante::Cerrada) {
            VacanteCerrada::dispatch($vacante);
        }

        return $vacante;
    }
}
