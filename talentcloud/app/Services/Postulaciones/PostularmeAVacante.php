<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoProcesamientoCv;
use App\Events\CandidatoSePostulo;
use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Postular con mi CV": el postulante se postula por su cuenta a una vacante
 * publicada. Aplica las reglas del autoservicio y delega en PostularCandidato,
 * así la postulación sigue el mismo camino que la de un reclutador (Kanban,
 * matching con IA, actividad).
 */
class PostularmeAVacante
{
    public const MAXIMO_POR_HORA = 20;

    public function __construct(
        private readonly VacanteRepository $vacantes,
        private readonly PostularCandidato $postular,
    ) {}

    public function ejecutar(User $postulante, int $vacanteId): Postulacion
    {
        $candidato = $postulante->candidato;

        if ($candidato === null) {
            throw new OperacionNoPermitida('Tu cuenta no tiene un perfil de postulante.');
        }

        // Se relee: la vacante pudo cerrarse, o su empresa suspenderse, mientras el postulante la miraba.
        $vacante = $this->vacantes->buscarPublicada($vacanteId);

        if ($vacante === null) {
            throw new OperacionNoPermitida('Esta vacante ya no recibe postulaciones.');
        }

        if ($candidato->postulacionesEnTodasLasEmpresas()->where('vacante_id', $vacante->id)->exists()) {
            throw new OperacionNoPermitida('Ya postulaste a esta vacante.');
        }

        $cv = $candidato->cvActual;

        if ($cv?->estado_procesamiento !== EstadoProcesamientoCv::Procesado) {
            throw new OperacionNoPermitida(self::motivoDelCv($cv));
        }

        $clave = 'postular|'.$postulante->id;

        if (RateLimiter::tooManyAttempts($clave, self::MAXIMO_POR_HORA)) {
            throw new OperacionNoPermitida('Alcanzaste el máximo de postulaciones por hora. Inténtalo más tarde.');
        }

        $postulacion = $this->postular->ejecutar($vacante, $candidato);
        RateLimiter::hit($clave, 3600);

        CandidatoSePostulo::dispatch($postulacion, $postulante);

        return $postulacion;
    }

    /** Por qué no se puede postular con el CV actual (se exige uno que la IA haya podido leer). */
    private static function motivoDelCv(?Cv $cv): string
    {
        return match ($cv?->estado_procesamiento) {
            null => 'Sube tu CV en "Mi currículo" para poder postular.',
            EstadoProcesamientoCv::Error => 'Tu CV no se pudo leer. Sube una versión en PDF con texto o en Word para postular.',
            default => 'Tu CV todavía se está analizando. Inténtalo en unos segundos.',
        };
    }
}
