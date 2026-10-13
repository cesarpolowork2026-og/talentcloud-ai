<?php

namespace App\Services\Vacantes;

use App\DTO\DatosVacanteData;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;

class ActualizarVacante
{
    public function __construct(private readonly VacanteRepository $vacantes) {}

    public function ejecutar(Vacante $vacante, DatosVacanteData $datos): Vacante
    {
        if (! $vacante->estado->esEditable()) {
            throw new OperacionNoPermitida('Una vacante cerrada no se puede modificar.');
        }

        $ubicacion = $datos->modalidad->normalizarUbicacion($datos->ubicacion);

        if ($ubicacion === null && $datos->modalidad->requiereUbicacion()) {
            throw new OperacionNoPermitida('Indica dónde se trabajará: una vacante '.mb_strtolower($datos->modalidad->etiqueta()).' necesita ubicación.', 'ubicacion');
        }

        return $this->vacantes->actualizar($vacante, [
            'titulo' => $datos->titulo,
            'descripcion' => $datos->descripcion,
            'ubicacion' => $ubicacion,
            'modalidad' => $datos->modalidad,
        ]);
    }
}
