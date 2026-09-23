<?php

namespace App\Services\Vacantes;

use App\DTO\NuevaVacanteData;
use App\Enums\EstadoVacante;
use App\Enums\TipoRequisito;
use App\Events\VacanteCreada;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Support\Facades\DB;

/**
 * "Nueva vacante": se crea en borrador con las competencias requeridas como
 * requisitos obligatorios de peso medio; se completan en el detalle antes de publicar.
 */
class CrearVacante
{
    public function __construct(private readonly VacanteRepository $vacantes) {}

    public function ejecutar(Empresa $empresa, NuevaVacanteData $datos, ?User $autor): Vacante
    {
        if (! $empresa->estaActiva()) {
            throw new OperacionNoPermitida('La empresa está suspendida.');
        }

        $vacante = DB::transaction(function () use ($empresa, $datos, $autor): Vacante {
            $vacante = $this->vacantes->crear($empresa, [
                'titulo' => $datos->titulo,
                'ubicacion' => $datos->ubicacion,
                'modalidad' => $datos->modalidad,
                'estado' => EstadoVacante::Borrador,
            ], $autor);

            foreach ($datos->competencias as $nombre) {
                $competencia = Competencia::obtenerOCrear($nombre);

                if (! $this->vacantes->tieneCompetencia($vacante, $competencia->id)) {
                    $this->vacantes->agregarRequisito($vacante, [
                        'competencia_id' => $competencia->id,
                        'tipo' => TipoRequisito::Obligatorio,
                        'peso' => 3,
                    ]);
                }
            }

            return $vacante;
        });

        VacanteCreada::dispatch($vacante, $autor);

        return $vacante;
    }
}
