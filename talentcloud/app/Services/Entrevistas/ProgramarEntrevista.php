<?php

namespace App\Services\Entrevistas;

use App\DTO\NuevaEntrevistaData;
use App\Enums\EtapaPostulacion;
use App\Enums\ModalidadEntrevista;
use App\Events\EntrevistaProgramada;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Programa una entrevista. La postulación solo se mueve en el Kanban si el
 * reclutador lo pide ($moverAEntrevista, marcado por defecto en el formulario)
 * y aún estaba antes de la entrevista: así el Kanban refleja lo que pasa en la
 * realidad sin que el sistema decida por la persona.
 */
class ProgramarEntrevista
{
    public function __construct(
        private readonly EntrevistaRepository $entrevistas,
        private readonly ReglasDeAgenda $reglas,
        private readonly MoverPostulacion $mover,
    ) {}

    public function ejecutar(NuevaEntrevistaData $datos, ?User $actor = null): Entrevista
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

        if ($datos->moverAEntrevista && $postulacion->etapa->antesDeEntrevista()) {
            $alFinal = Postulacion::query()
                ->where('vacante_id', $postulacion->vacante_id)
                ->where('etapa', EtapaPostulacion::Entrevista)
                ->count();

            $this->mover->ejecutar($postulacion, EtapaPostulacion::Entrevista, $alFinal, $actor);
        }

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
