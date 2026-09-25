<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\PostulacionCambioDeEtapa;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Mueve una postulación en el Kanban: a otra columna o a otra posición en
 * la misma. Reordena ambas columnas para que "orden" quede 0, 1, 2… sin
 * huecos. La IA no mueve postulaciones: esto solo lo hace una persona.
 */
class MoverPostulacion
{
    public function ejecutar(Postulacion $postulacion, EtapaPostulacion $destino, int $posicion, ?User $actor): Postulacion
    {
        $origen = $postulacion->etapa;

        $vacante = $postulacion->vacante;

        if ($vacante === null || $vacante->estado === EstadoVacante::Cerrada) {
            throw new OperacionNoPermitida('La vacante está cerrada: su Kanban es de solo lectura.');
        }

        // Retirarse es decisión del candidato: la empresa no puede devolverla al proceso.
        if ($postulacion->estaRetirada() && $destino !== EtapaPostulacion::Descartado) {
            throw new OperacionNoPermitida($postulacion->candidato?->nombre.' retiró su postulación: no se puede volver a mover.');
        }

        DB::transaction(function () use ($postulacion, $origen, $destino, $posicion): void {
            // Bloquea las postulaciones de la vacante para que dos movimientos simultáneos no mezclen el orden.
            $columnas = Postulacion::query()
                ->where('vacante_id', $postulacion->vacante_id)
                ->whereIn('etapa', array_unique([$origen->value, $destino->value]))
                ->orderBy('orden')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $restoOrigen = $columnas->where('etapa', $origen)->reject(fn (Postulacion $p) => $p->id === $postulacion->id)->values();
            $restoDestino = $columnas->where('etapa', $destino)->reject(fn (Postulacion $p) => $p->id === $postulacion->id)->values();

            $posicion = max(0, min($posicion, $restoDestino->count()));
            $nuevoDestino = $restoDestino->all();
            array_splice($nuevoDestino, $posicion, 0, [$postulacion]);

            if ($origen !== $destino) {
                $this->numerar($restoOrigen->all());
                $postulacion->etapa = $destino;
                $postulacion->etapa_cambiada_at = now();
            }

            $this->numerar($nuevoDestino);
        });

        if ($origen !== $destino) {
            PostulacionCambioDeEtapa::dispatch($postulacion, $origen, $destino, $actor);
        }

        return $postulacion;
    }

    /** "Descartar" del prototipo: al final de la columna Descartado; perfil y CV se conservan. */
    public function descartar(Postulacion $postulacion, ?User $actor): Postulacion
    {
        $alFinal = Postulacion::query()
            ->where('vacante_id', $postulacion->vacante_id)
            ->where('etapa', EtapaPostulacion::Descartado)
            ->count();

        return $this->ejecutar($postulacion, EtapaPostulacion::Descartado, $alFinal, $actor);
    }

    /**
     * @param  list<Postulacion>  $postulaciones
     */
    private function numerar(array $postulaciones): void
    {
        foreach ($postulaciones as $indice => $p) {
            if ($p->orden !== $indice || $p->isDirty()) {
                $p->orden = $indice;
                $p->save();
            }
        }
    }
}
