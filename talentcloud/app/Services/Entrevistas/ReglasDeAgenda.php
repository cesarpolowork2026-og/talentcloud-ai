<?php

namespace App\Services\Entrevistas;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * Reglas comunes al programar y al reprogramar una entrevista: la postulación
 * debe seguir en proceso y el entrevistador debe ser alguien activo de su
 * empresa. Lanzan OperacionNoPermitida con el campo del formulario afectado.
 */
class ReglasDeAgenda
{
    public function verificarPostulacion(Postulacion $postulacion): void
    {
        if ($postulacion->etapa === EtapaPostulacion::Descartado) {
            throw new OperacionNoPermitida('No se puede programar una entrevista a un candidato descartado.', 'postulacionId');
        }

        if (! in_array($postulacion->vacante?->estado, [EstadoVacante::Abierta, EstadoVacante::EnRevision], true)) {
            throw new OperacionNoPermitida('La vacante '.$postulacion->vacante?->titulo.' no está abierta.', 'postulacionId');
        }
    }

    public function verificarEntrevistador(?User $entrevistador, Postulacion $postulacion): void
    {
        $valido = $entrevistador !== null
            && $entrevistador->empresa_id === $postulacion->empresa_id
            && in_array($entrevistador->rol(), [Rol::Empresa, Rol::Reclutador], true)
            && $entrevistador->estaActivo();

        if (! $valido) {
            throw new OperacionNoPermitida('El entrevistador debe ser un usuario activo de la empresa de la vacante.', 'entrevistadorId');
        }
    }
}
