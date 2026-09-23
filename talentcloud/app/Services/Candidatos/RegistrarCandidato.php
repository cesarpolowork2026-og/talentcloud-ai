<?php

namespace App\Services\Candidatos;

use App\DTO\NuevoCandidatoData;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Repositories\Contracts\CandidatoRepository;
use InvalidArgumentException;

/**
 * Un reclutador registra a un candidato que no tiene cuenta. Queda como
 * "registrado por" su empresa (así lo ve), con la constancia de que autorizó
 * el tratamiento de sus datos.
 */
class RegistrarCandidato
{
    public function __construct(private readonly CandidatoRepository $candidatos) {}

    public function ejecutar(Empresa $empresa, NuevoCandidatoData $datos): Candidato
    {
        if (! $datos->autorizoTratamientoDeDatos) {
            throw new InvalidArgumentException('Se necesita la autorización del candidato para tratar sus datos.');
        }

        return $this->candidatos->crear([
            'empresa_origen_id' => $empresa->id,
            'nombre' => $datos->nombre,
            'email' => $datos->email ? mb_strtolower($datos->email) : null,
            'telefono' => $datos->telefono,
            'titular' => $datos->titular,
            'consentimiento_at' => now(),
        ]);
    }
}
