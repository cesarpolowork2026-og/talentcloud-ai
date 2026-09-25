<?php

namespace App\Listeners;

use App\Enums\EtapaPostulacion;
use App\Enums\TipoActividad;
use App\Events\CandidatoSePostulo;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Events\EntrevistaReprogramada;
use App\Events\EvaluacionAprobada;
use App\Events\NotaAgregada;
use App\Events\PlanEmpresaCambiado;
use App\Events\PostulacionCambioDeEtapa;
use App\Events\PostulacionRetirada;
use App\Events\VacanteCreada;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Actividad\RegistroDeActividad;
use App\Support\Formato;

/** "Actividad reciente" del dashboard: una línea legible por cada hecho relevante. */
class AnotarActividad
{
    public function __construct(private readonly RegistroDeActividad $actividad) {}

    public function handleEtapa(PostulacionCambioDeEtapa $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing(['candidato', 'vacante']);

        // La retirada tiene su propia línea (handleRetirada): no es un descarte de la empresa.
        if ($postulacion->estaRetirada()) {
            return;
        }

        $nombre = $postulacion->candidato?->nombre;

        $descripcion = match (true) {
            // Sin género gramatical: no se conoce ni se deduce el del candidato.
            $evento->destino === EtapaPostulacion::Descartado => "Se descartó a {$nombre} de {$postulacion->vacante?->titulo}.",
            $this->avanza($evento->origen, $evento->destino) => "{$nombre} avanzó a la etapa de ".mb_strtolower($evento->destino->etiqueta()).'.',
            default => "{$nombre} volvió a la etapa de ".mb_strtolower($evento->destino->etiqueta()).'.',
        };

        $this->anotar($postulacion, TipoActividad::EtapaCambiada, $descripcion, $evento->actor);
    }

    public function handleCandidatoSePostulo(CandidatoSePostulo $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing('candidato');
        // El postulante no tiene contexto de empresa: la vacante se lee sin ese filtro, por su id.
        $titulo = Vacante::sinFiltroDeEmpresa()->whereKey($postulacion->vacante_id)->value('titulo');

        $this->anotar(
            $postulacion,
            TipoActividad::CandidatoSePostulo,
            "{$postulacion->candidato?->nombre} postuló a {$titulo}.",
            $evento->postulante,
        );
    }

    public function handleRetirada(PostulacionRetirada $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing('candidato');
        $titulo = Vacante::sinFiltroDeEmpresa()->whereKey($postulacion->vacante_id)->value('titulo');

        $this->anotar(
            $postulacion,
            TipoActividad::PostulacionRetirada,
            "{$postulacion->candidato?->nombre} retiró su postulación a {$titulo}.",
            $evento->postulante,
        );
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

    public function handleNota(NotaAgregada $evento): void
    {
        $nota = $evento->nota->loadMissing(['autor', 'postulacion.candidato']);

        if ($nota->postulacion !== null) {
            $this->anotar(
                $nota->postulacion,
                TipoActividad::NotaAgregada,
                "{$nota->autor?->name} dejó una nota sobre {$nota->postulacion->candidato?->nombre}.",
                $nota->autor,
            );
        }
    }

    public function handleEntrevistaReprogramada(EntrevistaReprogramada $evento): void
    {
        $entrevista = $evento->entrevista->loadMissing('postulacion.candidato');

        if ($entrevista->postulacion !== null) {
            $this->anotar(
                $entrevista->postulacion,
                TipoActividad::EntrevistaReprogramada,
                "Se reprogramó la entrevista de {$entrevista->postulacion->candidato?->nombre} al ".Formato::fechaHora($entrevista->inicia_at).'.',
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

    public function handlePlan(PlanEmpresaCambiado $evento): void
    {
        $empresa = $evento->empresa;

        $this->actividad->registrar(
            $empresa->id,
            TipoActividad::PlanCambiado,
            "{$empresa->nombre} pasó del plan {$evento->anterior->etiqueta()} al plan {$empresa->plan->etiqueta()}.",
            $empresa,
            $evento->actor,
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
