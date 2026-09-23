<?php

namespace App\Services\Vacantes;

use App\DTO\RequisitoData;
use App\Models\Competencia;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;

/**
 * Editor de requisitos: la competencia se elige del catálogo o se da de alta
 * al vuelo ("alta rápida"); una competencia no se repite en la misma vacante.
 */
class GestionarRequisitos
{
    public function __construct(private readonly VacanteRepository $vacantes) {}

    public function agregar(Vacante $vacante, RequisitoData $datos): Requisito
    {
        $this->asegurarEditable($vacante);

        $competencia = Competencia::obtenerOCrear($datos->competencia);

        if ($this->vacantes->tieneCompetencia($vacante, $competencia->id)) {
            throw new OperacionNoPermitida('La vacante ya tiene un requisito de '.$competencia->nombre.'.');
        }

        return $this->vacantes->agregarRequisito($vacante, [
            'competencia_id' => $competencia->id,
            ...$this->atributos($datos),
        ]);
    }

    public function actualizar(Vacante $vacante, Requisito $requisito, RequisitoData $datos): Requisito
    {
        $this->asegurarEditable($vacante);

        $competencia = Competencia::obtenerOCrear($datos->competencia);

        if ($this->vacantes->tieneCompetencia($vacante, $competencia->id, $requisito->id)) {
            throw new OperacionNoPermitida('La vacante ya tiene un requisito de '.$competencia->nombre.'.');
        }

        $requisito->update([
            'competencia_id' => $competencia->id,
            ...$this->atributos($datos),
        ]);

        return $requisito;
    }

    public function quitar(Vacante $vacante, Requisito $requisito): void
    {
        $this->asegurarEditable($vacante);

        $requisito->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function atributos(RequisitoData $datos): array
    {
        return [
            'tipo' => $datos->tipo,
            'peso' => max(1, min(5, $datos->peso)),
            'anios_minimos' => $datos->aniosMinimos,
            'descripcion' => $datos->descripcion,
        ];
    }

    private function asegurarEditable(Vacante $vacante): void
    {
        if (! $vacante->estado->esEditable()) {
            throw new OperacionNoPermitida('Una vacante cerrada no se puede modificar.');
        }
    }
}
