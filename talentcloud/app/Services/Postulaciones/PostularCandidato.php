<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use App\Events\PostulacionCreada;
use App\Models\Candidato;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Crea la postulación de un candidato a una vacante abierta: entra en la
 * columna "Postulado" del Kanban, al final, con su CV más reciente. El
 * origen distingue lo que registró el equipo de lo que postuló el candidato.
 */
class PostularCandidato
{
    public function ejecutar(Vacante $vacante, Candidato $candidato, OrigenPostulacion $origen = OrigenPostulacion::Equipo): Postulacion
    {
        if ($vacante->estado !== EstadoVacante::Abierta) {
            throw new OperacionNoPermitida('Solo se puede postular a vacantes abiertas.');
        }

        try {
            $postulacion = DB::transaction(fn (): Postulacion => $this->crear($vacante, $candidato, $origen));
        } catch (UniqueConstraintViolationException) {
            // Dos envíos simultáneos: el índice único (vacante_id, candidato_id) frenó al segundo.
            throw new OperacionNoPermitida($candidato->nombre.' ya postuló a '.$vacante->titulo.'.');
        }

        PostulacionCreada::dispatch($postulacion);

        return $postulacion;
    }

    private function crear(Vacante $vacante, Candidato $candidato, OrigenPostulacion $origen): Postulacion
    {
        // Se bloquea la fila de la vacante (como al mover una tarjeta): las postulaciones de una vacante se crean
        // de una en una, así no se repite el orden ni se postula a una vacante que se cerró un instante antes.
        $bloqueada = Vacante::sinFiltroDeEmpresa()->whereKey($vacante->id)->lockForUpdate()->first();

        if ($bloqueada === null || $bloqueada->estado !== EstadoVacante::Abierta) {
            throw new OperacionNoPermitida('Solo se puede postular a vacantes abiertas.');
        }

        $existe = Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $vacante->id)
            ->where('candidato_id', $candidato->id)
            ->exists();

        if ($existe) {
            throw new OperacionNoPermitida($candidato->nombre.' ya postuló a '.$vacante->titulo.'.');
        }

        $orden = (int) Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $vacante->id)
            ->where('etapa', EtapaPostulacion::Postulado)
            ->max('orden') + 1;

        $postulacion = new Postulacion([
            'vacante_id' => $vacante->id,
            'candidato_id' => $candidato->id,
            'cv_id' => $candidato->cvs()->latest()->value('id'),
            'etapa' => EtapaPostulacion::Postulado,
            'orden' => $orden,
            'etapa_cambiada_at' => now(),
            'origen' => $origen,
        ]);
        $postulacion->empresa_id = $vacante->empresa_id;
        $postulacion->save();

        return $postulacion;
    }
}
