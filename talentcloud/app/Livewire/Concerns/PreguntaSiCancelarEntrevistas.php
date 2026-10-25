<?php

namespace App\Livewire\Concerns;

use App\Enums\EtapaPostulacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\RetrocesoConEntrevistas;
use Livewire\Attributes\Locked;

/**
 * Mover una postulación y, si se la devuelve antes de «Entrevista» con entrevistas por realizar, guardar el movimiento
 * y preguntar si se cancelan (<x-postulaciones.retroceso-con-entrevistas>). La respuesta llega a confirmarRetroceso()
 * de cada pantalla, que repite el movimiento con la decisión.
 */
trait PreguntaSiCancelarEntrevistas
{
    public bool $mostrarRetroceso = false;

    /** @var array{postulacion: int, destino: string, posicion: int, nombre: string, cuantas: int}|null */
    #[Locked]
    public ?array $retroceso = null;

    /** true si se movió; false si quedó la pregunta abierta. Las demás reglas (OperacionNoPermitida) siguen subiendo. */
    protected function moverOPreguntar(MoverPostulacion $mover, Postulacion $postulacion, EtapaPostulacion $destino, int $posicion, User $actor): bool
    {
        try {
            $mover->ejecutar($postulacion, $destino, $posicion, $actor);
        } catch (RetrocesoConEntrevistas $e) {
            $this->retroceso = [
                'postulacion' => $e->postulacionId,
                'destino' => $e->destino->value,
                'posicion' => $posicion,
                'nombre' => $e->nombre,
                'cuantas' => $e->cuantas,
            ];
            $this->mostrarRetroceso = true;

            return false;
        }

        return true;
    }

    /**
     * El movimiento pendiente (y se cierra la pregunta), o null si no hay ninguno.
     *
     * @return array{postulacion: int, destino: EtapaPostulacion, posicion: int}|null
     */
    protected function tomarRetroceso(): ?array
    {
        $pendiente = $this->retroceso;
        $this->retroceso = null;
        $this->mostrarRetroceso = false;

        return $pendiente === null ? null : [
            'postulacion' => $pendiente['postulacion'],
            'destino' => EtapaPostulacion::from($pendiente['destino']),
            'posicion' => $pendiente['posicion'],
        ];
    }
}
