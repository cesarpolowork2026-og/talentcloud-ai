<?php

namespace App\Listeners;

use App\Enums\EtapaPostulacion;
use App\Enums\TipoActividad;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Events\EvaluacionAprobada;
use App\Events\PostulacionCambioDeEtapa;
use App\Events\VacanteCreada;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Actividad\RegistroDeActividad;
use App\Support\Formato;

/** "Actividad reciente" del dashboard: una línea legible por cada hecho relevante. */
class AnotarActividad
{
    public function __construct(private readonly RegistroDeActividad $actividad) {}

    public function handleEtapa(PostulacionCambioDeEtapa $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing(['candidato', 'vacante']);
        $nombre = $postulacion->candidato?->nombre;

        $descripcion = match (true) {
            // Sin género gramatical: no se conoce ni se deduce el del candidato.
            $evento->destino === EtapaPostulacion::Descartado => "Se descartó a {$nombre} de {$postulacion->vacante?->titulo}.",
            $this->avanza($evento->origen, $evento->destino) => "{$nombre} avanzó a la etapa de ".mb_strtolower($evento->destino->etiqueta()).'.',
            default => "{$nombre} volvió a la etapa de ".mb_strtolower($evento->destino->etiqueta()).'.',
        };

        $this->anotar($postulacion, TipoActividad::EtapaCambiada, $descripcion, $evento->actor);
    }

    public function handleCv(CvAnalizado $evento): void
    {
        if (! $evento->primeraVez) {
            return;
        }

        // El CV es global: se anota en cada empresa donde el candidato tiene una postulación.
        Postulacion::sinFiltroDeEmpresa()
            ->with(['candidato', 'vacante' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('candidato_id', $evento->cv->candidato_id)
            ->get()
            ->each(fn (Postulacion $p) => $this->anotar(
                $p,
                TipoActividad::CvAnalizado,
                "La IA analizó el CV de {$p->candidato?->nombre} para {$p->vacante?->titulo}.",
            ));
    }

    public function handleEvaluacion(EvaluacionAprobada $evento): void
    {
        $evaluacion = $evento->evaluacion->loadMissing(['evaluador', 'postulacion.candidato']);

        if ($evaluacion->postulacion !== null) {
            $this->anotar(
                $evaluacion->postulacion,
                TipoActividad::EvaluacionAprobada,
                "{$evaluacion->evaluador?->name} aprobó la evaluación de {$evaluacion->postulacion->candidato?->nombre}.",
                $evaluacion->evaluador,
            );
        }
    }

    public function handleEntrevistaProgramada(EntrevistaProgramada $evento): void
    {
        $entrevista = $evento->entrevista->loadMissing(['entrevistador', 'postulacion.candidato']);
        $quien = $this->usuarioActual() ?? $entrevista->entrevistador;

        if ($entrevista->postulacion !== null) {
            $this->anotar(
                $entrevista->postulacion,
                TipoActividad::EntrevistaProgramada,
                "{$quien?->name} programó una entrevista con {$entrevista->postulacion->candidato?->nombre} para el ".Formato::fechaHora($entrevista->inicia_at).'.',
                $quien,
            );
        }
    }

    public function handleEntrevistaCancelada(EntrevistaCancelada $evento): void
    {
        $entrevista = $evento->entrevista->loadMissing('postulacion.candidato');

        if ($entrevista->postulacion !== null) {
            $this->anotar(
                $entrevista->postulacion,
                TipoActividad::EntrevistaCancelada,
                "Se canceló la entrevista de {$entrevista->postulacion->candidato?->nombre} del ".Formato::fechaHora($entrevista->inicia_at).'.',
            );
        }
    }

    public function handleVacante(VacanteCreada $evento): void
    {
        $vacante = $evento->vacante->loadMissing('empresa');

        $this->actividad->registrar(
            $vacante->empresa_id,
            TipoActividad::VacanteCreada,
            "{$vacante->empresa?->nombre} creó la vacante {$vacante->titulo}.",
            $vacante,
            $evento->autor,
        );
    }

    private function anotar(Postulacion $postulacion, TipoActividad $tipo, string $descripcion, ?User $actor = null): void
    {
        $this->actividad->registrar($postulacion->empresa_id, $tipo, $descripcion, $postulacion, $actor ?? $this->usuarioActual());
    }

    private function avanza(EtapaPostulacion $origen, EtapaPostulacion $destino): bool
    {
        $orden = EtapaPostulacion::cases();

        return array_search($destino, $orden, true) > array_search($origen, $orden, true);
    }

    private function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
