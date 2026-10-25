<?php

namespace App\Services\Postulaciones;

use App\Enums\EtapaPostulacion;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * Devolver antes de «Entrevista» a quien tiene entrevistas por realizar: la persona decide si se cancelan o se
 * mantienen (MoverPostulacion con $cancelarEntrevistas). La pantalla lo pregunta; quien no lo maneje ve el mensaje.
 */
class RetrocesoConEntrevistas extends OperacionNoPermitida
{
    public function __construct(
        public readonly int $postulacionId,
        public readonly string $nombre,
        public readonly EtapaPostulacion $destino,
        public readonly int $cuantas,
    ) {
        parent::__construct($nombre.' tiene '.EntrevistasPorRealizar::texto($cuantas).' por realizar: indica si '
            .EntrevistasPorRealizar::segun($cuantas, 'se cancela', 'se cancelan').' antes de moverla a «'.$destino->etiqueta().'».');
    }
}
