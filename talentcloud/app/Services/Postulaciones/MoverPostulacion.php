<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\PostulacionCambioDeEtapa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Mueve una postulación en el Kanban: a otra columna o a otra posición en
 * la misma. Reordena ambas columnas para que "orden" quede 0, 1, 2… sin
 * huecos, en SQL (escala a miles de postulaciones por columna). Mover una postulación solo lo hace una persona.
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
            throw new OperacionNoPermitida($postulacion->loadMissing('candidato')->candidato?->nombre.' retiró su postulación: no se puede volver a mover.');
        }

        DB::transaction(function () use ($postulacion, $origen, $destino, $posicion): void {
            // Un candado sobre la vacante basta para que dos movimientos simultáneos no mezclen el orden:
            // no se leen ni se bloquean las postulaciones (una vacante puede tener miles en una columna).
            Vacante::query()->whereKey($postulacion->vacante_id)->lockForUpdate()->first();

            $this->verificarDescarte($postulacion, $origen, $destino);
            $this->verificarAvanceFinal($postulacion, $origen, $destino);

            // Se saca de su columna y se deja el resto numerado 0, 1, 2… sin huecos (en SQL, solo se escriben
            // las filas que cambian de número); lo mismo en la columna destino si es otra.
            $this->numerar($postulacion, $origen);
            if ($destino !== $origen) {
                $this->numerar($postulacion, $destino);
            }

            $enDestino = Postulacion::query()
                ->where('vacante_id', $postulacion->vacante_id)
                ->where('etapa', $destino)
                ->whereKeyNot($postulacion->id)
                ->count();
            $posicion = max(0, min($posicion, $enDestino));

            // Hace lugar en la posición elegida y coloca la postulación con save(): el historial de etapas
            // lo escribe el observador al cambiar `etapa`.
            DB::update(
                'UPDATE postulaciones SET orden = orden + 1 WHERE empresa_id = ? AND vacante_id = ? AND etapa = ? AND id <> ? AND orden >= ?',
                [$postulacion->empresa_id, $postulacion->vacante_id, $destino->value, $postulacion->id, $posicion],
            );

            if ($origen !== $destino) {
                $postulacion->etapa = $destino;
                $postulacion->etapa_cambiada_at = now();
            }
            $postulacion->orden = $posicion;
            $postulacion->save();
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
     * Descartar a quien tiene entrevistas por realizar lo dejaría esperando una
     * entrevista que no va a ocurrir: primero se cancelan (con su aviso). La
     * retirada no pasa por aquí: la decide el candidato y RetirarPostulacion
     * cancela sus entrevistas.
     */
    private function verificarDescarte(Postulacion $postulacion, EtapaPostulacion $origen, EtapaPostulacion $destino): void
    {
        if ($destino !== EtapaPostulacion::Descartado || $origen === EtapaPostulacion::Descartado || $postulacion->estaRetirada()) {
            return;
        }

        $porRealizar = EntrevistasPorRealizar::consulta()->where('postulacion_id', $postulacion->id)->count();

        if ($porRealizar > 0) {
            throw new OperacionNoPermitida(
                $postulacion->loadMissing('candidato')->candidato?->nombre.' tiene '.EntrevistasPorRealizar::texto($porRealizar).' por realizar. '
                .EntrevistasPorRealizar::segun($porRealizar, 'Cancélala', 'Cancélalas').' en «Entrevistas» y vuelve a descartar.'
            );
        }
    }

    /**
     * Oferta y Contratado son decisiones sobre alguien entrevistado: sin una entrevista realizada, un arrastre por
     * error desde «Postulado» terminaría en un aviso de «¡Felicitaciones!» (la misma regla que exige evaluar).
     * Al contratar no puede quedar una entrevista pendiente: se completa o se cancela antes.
     */
    private function verificarAvanceFinal(Postulacion $postulacion, EtapaPostulacion $origen, EtapaPostulacion $destino): void
    {
        if (! in_array($destino, [EtapaPostulacion::Oferta, EtapaPostulacion::Contratado], true) || $destino === $origen) {
            return;
        }

        $nombre = (string) $postulacion->loadMissing('candidato')->candidato?->nombre;

        if (! $postulacion->entrevistas()->where('estado', EstadoEntrevista::Completada)->exists()) {
            throw new OperacionNoPermitida(
                $nombre.' todavía no tiene una entrevista realizada. Para pasar a «'.$destino->etiqueta().'», programa la entrevista y márcala como realizada.'
            );
        }

        if ($destino === EtapaPostulacion::Contratado) {
            $porRealizar = EntrevistasPorRealizar::consulta()->where('postulacion_id', $postulacion->id)->count();

            if ($porRealizar > 0) {
                throw new OperacionNoPermitida(
                    $nombre.' tiene '.EntrevistasPorRealizar::texto($porRealizar).' por realizar. '
                    .EntrevistasPorRealizar::segun($porRealizar, 'Márcala como realizada o cancélala', 'Márcalas como realizadas o cancélalas').' en «Entrevistas» antes de contratar.'
                );
            }
        }
    }

    /**
     * Numera 0, 1, 2… la columna `$etapa` de la vacante sin contar la postulación que se mueve.
     * Una sola sentencia: solo actualiza las filas cuyo número cambia.
     */
    private function numerar(Postulacion $movida, EtapaPostulacion $etapa): void
    {
        DB::update(
            'UPDATE postulaciones p SET orden = n.nuevo
             FROM (SELECT id, row_number() OVER (ORDER BY orden, id) - 1 AS nuevo
                   FROM postulaciones WHERE empresa_id = ? AND vacante_id = ? AND etapa = ? AND id <> ?) n
             WHERE p.id = n.id AND p.orden <> n.nuevo',
            [$movida->empresa_id, $movida->vacante_id, $etapa->value, $movida->id],
        );
    }
}
