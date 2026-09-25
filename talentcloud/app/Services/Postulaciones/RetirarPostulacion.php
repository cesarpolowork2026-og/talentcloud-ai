<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\PostulacionRetirada;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * El postulante retira su postulación. Primero se fija retirada_at y después
 * se mueve a Descartado con MoverPostulacion: así los listeners del cambio de
 * etapa ya saben que no es un descarte de la empresa. Sus entrevistas
 * pendientes o confirmadas se cancelan (con su aviso al entrevistador).
 *
 * El postulante no tiene contexto de empresa: todo se hace en el de la
 * empresa de la postulación, que ya se comprobó que es suya (Policy).
 */
class RetirarPostulacion
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly MoverPostulacion $mover,
        private readonly CambiarEstadoEntrevista $entrevistas,
    ) {}

    public function ejecutar(Postulacion $postulacion, User $postulante): Postulacion
    {
        return $this->tenant->ejecutarPara($postulacion->empresa_id, function () use ($postulacion, $postulante): Postulacion {
            $postulacion = DB::transaction(function () use ($postulacion, $postulante): Postulacion {
                // Se relee bloqueada: el reclutador pudo moverla mientras el postulante decidía.
                $actual = Postulacion::query()->with('vacante')->lockForUpdate()->findOrFail($postulacion->id);

                $this->verificar($actual);

                $actual->retirada_at = now();
                $actual->save();

                return $this->mover->descartar($actual, $postulante);
            });

            Entrevista::query()
                ->where('postulacion_id', $postulacion->id)
                ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
                ->get()
                ->each(fn (Entrevista $e) => $this->entrevistas->cancelar($e));

            PostulacionRetirada::dispatch($postulacion, $postulante);

            return $postulacion;
        });
    }

    private function verificar(Postulacion $postulacion): void
    {
        $motivo = match (true) {
            $postulacion->estaRetirada() => 'Ya retiraste esta postulación.',
            $postulacion->etapa === EtapaPostulacion::Contratado => 'Ya fuiste contratado en este proceso: habla con la empresa.',
            $postulacion->etapa === EtapaPostulacion::Descartado => 'Este proceso ya terminó para ti.',
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => 'La vacante ya está cerrada.',
            default => null,
        };

        if ($motivo !== null) {
            throw new OperacionNoPermitida($motivo, 'retiro');
        }
    }
}
