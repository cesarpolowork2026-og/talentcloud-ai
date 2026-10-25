<?php

namespace App\Jobs;

use App\Enums\EstadoDeMiPostulacion;
use App\Models\Postulacion;
use App\Notifications\AvisoEstadoDePostulacion;
use App\Services\Avisos\AvisoAlCandidato;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use App\Tenancy\ConEmpresa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Avisa al candidato del estado de su postulación. Se programa con demora
 * (config talentcloud.avisos.demora_minutos) y, al ejecutarse, relee la
 * postulación: solo avisa si su estado ACTUAL se comunica y aún no se le
 * comunicó. Así, un arrastre accidental deshecho a tiempo no llega a avisarse
 * y varios movimientos seguidos producen un único aviso.
 */
class AvisarEstadoDePostulacion implements ShouldQueue
{
    use ConEmpresa, Queueable;

    public int $tries = 3;

    public function __construct(public int $empresaId, public int $postulacionId)
    {
        $this->afterCommit();
    }

    /** Programa el aviso de una postulación con la demora configurada. */
    public static function programar(Postulacion $postulacion): void
    {
        self::dispatch($postulacion->empresa_id, $postulacion->id)
            ->delay(now()->addMinutes(config('talentcloud.avisos.demora_minutos')));
    }

    public function handle(AvisoAlCandidato $avisos): void
    {
        DB::transaction(function () use ($avisos): void {
            // Bloqueo: dos avisos programados de la misma postulación no avisan dos veces.
            $postulacion = Postulacion::query()->lockForUpdate()->find($this->postulacionId);

            if ($postulacion === null) {
                return;
            }

            $postulacion->load(['vacante.empresa', 'candidato.user']);
            $estado = EstadoDeMiPostulacion::aAvisar($postulacion);

            if ($estado === null || $estado === $postulacion->estado_avisado) {
                return;
            }

            $anterior = $postulacion->estado_avisado;
            $postulacion->estado_avisado = $estado;
            $postulacion->save();

            // Con una entrevista ya programada, el aviso de la entrevista (fecha y hora) ya le dijo que está en esa etapa.
            if ($estado === EstadoDeMiPostulacion::Entrevista
                && EntrevistasPorRealizar::consulta()->where('postulacion_id', $postulacion->id)->exists()) {
                return;
            }

            $avisos->enviar($postulacion->candidato, AvisoEstadoDePostulacion::de(
                $estado,
                (string) $postulacion->vacante?->titulo,
                (string) $postulacion->vacante?->empresa?->nombre,
                AvisoAlCandidato::urlPara($postulacion->candidato),
                $anterior,
            ));
        });
    }
}
