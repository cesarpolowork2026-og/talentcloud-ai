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

        $postulacion = DB::transaction(function () use ($vacante, $candidato, $origen): Postulacion {
            // El índice único (vacante_id, candidato_id) es la garantía final ante envíos simultáneos.
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
        });

        PostulacionCreada::dispatch($postulacion);

        return $postulacion;
    }
}
