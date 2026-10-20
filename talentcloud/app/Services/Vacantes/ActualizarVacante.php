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

        if ($datos->sueldoMinimo !== null && $datos->sueldoMaximo !== null && $datos->sueldoMaximo < $datos->sueldoMinimo) {
            throw new OperacionNoPermitida('El sueldo máximo no puede ser menor que el mínimo.', 'sueldoMaximo');
        }

        return $this->vacantes->actualizar($vacante, [
            'titulo' => $datos->titulo,
            'descripcion' => $datos->descripcion,
            'ubicacion' => $ubicacion,
            'modalidad' => $datos->modalidad,
            'jornada' => $datos->jornada,
            'sueldo_minimo' => $datos->sueldoMinimo,
            'sueldo_maximo' => $datos->sueldoMaximo,
        ]);
    }
}
