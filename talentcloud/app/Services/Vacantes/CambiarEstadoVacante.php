<?php

namespace App\Services\Vacantes;

use App\Enums\EstadoVacante;
use App\Events\VacanteCerrada;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;

/** Transiciones de estado de una vacante: Borrador → Abierta ⇄ En revisión → Cerrada. */
class CambiarEstadoVacante
{
    public function __construct(private readonly VacanteRepository $vacantes) {}

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

        $vacante = $this->vacantes->actualizar($vacante, $datos);

        if ($destino === EstadoVacante::Cerrada) {
            VacanteCerrada::dispatch($vacante);
        }

        return $vacante;
    }
}
