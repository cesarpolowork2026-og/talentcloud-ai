<?php

namespace App\Services\Evaluaciones;

use App\DTO\EvaluacionData;
use App\Enums\EstadoEvaluacion;
use App\Events\EvaluacionAprobada;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EvaluacionRepository;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registra o actualiza una evaluación por criterios. El puntaje es el promedio
 * de los criterios (0–10), calculado aquí y no en el formulario.
 */
class GuardarEvaluacion
{
    public function __construct(private readonly EvaluacionRepository $evaluaciones) {}

    public function registrar(int $postulacionId, User $evaluador, EvaluacionData $datos): Evaluacion
    {
        // Con el filtro por empresa, una postulación ajena no se encuentra.
        $postulacion = Postulacion::with(['candidato', 'vacante'])->find($postulacionId)
            ?? throw new OperacionNoPermitida('Elige una postulación de la lista.', 'postulacionId');

        if ($this->evaluaciones->existe($postulacion->id, $evaluador->id)) {
            throw $this->yaEvaluada($postulacion);
        }

        $this->validar($datos);

        try {
            $evaluacion = $this->evaluaciones->crear($postulacion, $evaluador, $this->atributos($datos));
        } catch (UniqueConstraintViolationException) {
            throw $this->yaEvaluada($postulacion); // otra petición la registró a la vez
        }

        if ($evaluacion->estado === EstadoEvaluacion::Aprobado) {
            EvaluacionAprobada::dispatch($evaluacion);
        }

        return $evaluacion;
    }

    public function actualizar(Evaluacion $evaluacion, EvaluacionData $datos): Evaluacion
    {
        $this->validar($datos);

        $estabaAprobada = $evaluacion->estado === EstadoEvaluacion::Aprobado;
        $evaluacion->update($this->atributos($datos));

        if (! $estabaAprobada && $datos->estado === EstadoEvaluacion::Aprobado) {
            EvaluacionAprobada::dispatch($evaluacion);
        }

        return $evaluacion;
    }

    private function validar(EvaluacionData $datos): void
    {
        if ($datos->criteriosNoValidos() !== []) {
            throw new OperacionNoPermitida('Puntúa cada criterio de 0 a 10.', 'puntajes');
        }
    }

    /** @return array<string, mixed> */
    private function atributos(EvaluacionData $datos): array
    {
        $criterios = $datos->criterios();

        return [
            'criterios' => $criterios,
            'puntaje' => Evaluacion::promediar($criterios),
            'comentario' => $datos->comentario,
            'estado' => $datos->estado,
        ];
    }

    private function yaEvaluada(Postulacion $postulacion): OperacionNoPermitida
    {
        return new OperacionNoPermitida(
            'Ya evaluaste a '.$postulacion->candidato?->nombre.' para '.$postulacion->vacante?->titulo.': edita tu evaluación.',
            'postulacionId',
        );
    }
}
