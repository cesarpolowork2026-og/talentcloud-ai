<?php

namespace App\Services\Entrevistas;

use App\DTO\NuevaEntrevistaData;
use App\Enums\ModalidadEntrevista;
use App\Events\EntrevistaProgramada;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Programa una entrevista. No mueve la postulación en el Kanban: eso lo decide
 * el reclutador.
 */
class ProgramarEntrevista
{
    public function __construct(
        private readonly EntrevistaRepository $entrevistas,
        private readonly ReglasDeAgenda $reglas,
    ) {}

    public function ejecutar(NuevaEntrevistaData $datos): Entrevista
    {
        $postulacion = $this->postulacion($datos->postulacionId);

        if ($datos->iniciaAt->lessThanOrEqualTo(now())) {
            throw new OperacionNoPermitida('La entrevista debe ser en una fecha y hora futuras.', 'iniciaAt');
        }

        $entrevista = DB::transaction(function () use ($datos, $postulacion) {
            // Bloquea al entrevistador: dos programaciones a la vez no pasan ambas la comprobación de agenda.
            $entrevistador = User::whereKey($datos->entrevistadorId)->lockForUpdate()->first();
            $this->reglas->verificarEntrevistador($entrevistador, $postulacion);

            if ($this->entrevistas->haySolapamiento($datos->entrevistadorId, $datos->iniciaAt, $datos->terminaAt())) {
                throw new OperacionNoPermitida($entrevistador?->name.' ya tiene una entrevista en ese horario.', 'iniciaAt');
            }

            return $this->entrevistas->crear($postulacion, [
                'entrevistador_id' => $datos->entrevistadorId,
                'inicia_at' => $datos->iniciaAt,
                'duracion_min' => $datos->duracionMin,
                'modalidad' => $datos->modalidad,
                // El enlace solo tiene sentido en una videollamada.
                'enlace' => $datos->modalidad === ModalidadEntrevista::Videollamada ? $datos->enlace : null,
                'notas' => $datos->notas,
            ]);
        });

        EntrevistaProgramada::dispatch($entrevista);

        return $entrevista;
    }

    private function postulacion(int $id): Postulacion
    {
        // Con el filtro por empresa, una postulación ajena no se encuentra.
        $postulacion = Postulacion::with(['vacante', 'candidato'])->find($id);

        if ($postulacion === null) {
            throw new OperacionNoPermitida('Elige una postulación de la lista.', 'postulacionId');
        }

        $this->reglas->verificarPostulacion($postulacion);

        return $postulacion;
    }
}
