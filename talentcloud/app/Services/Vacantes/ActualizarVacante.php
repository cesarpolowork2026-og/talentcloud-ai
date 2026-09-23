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

        return $this->vacantes->actualizar($vacante, [
            'titulo' => $datos->titulo,
            'descripcion' => $datos->descripcion,
            'ubicacion' => $datos->ubicacion,
            'modalidad' => $datos->modalidad,
        ]);
    }
}
