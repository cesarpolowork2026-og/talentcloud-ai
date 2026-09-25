<?php

namespace App\Listeners;

use App\DTO\DatosDeEntrevista;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Events\CandidatoSePostulo;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Events\EntrevistaReprogramada;
use App\Events\PostulacionCambioDeEtapa;
use App\Events\VacanteCerrada;
use App\Jobs\AvisarEstadoDePostulacion;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoEntrevistaReprogramada;
use App\Notifications\AvisoNuevaPostulacion;
use App\Services\Avisos\AvisoAlCandidato;
use Illuminate\Notifications\Notification;

/**
 * Notificaciones (campana + correo según preferencia): entrevista programada o
 * cancelada al candidato y al entrevistador, CV analizado a quien lo subió,
 * postulación propia del candidato a la empresa, y cambios de estado y cierre
 * del proceso al candidato (diferidos: AvisarEstadoDePostulacion). Nadie recibe
 * aviso de lo que hizo él mismo.
 */
class EnviarAvisos
{
    public function __construct(private readonly AvisoAlCandidato $avisoAlCandidato) {}

    public function handleEntrevistaProgramada(EntrevistaProgramada $evento): void
    {
        $entrevista = $this->cargar($evento->entrevista);
        $datos = DatosDeEntrevista::de($entrevista);

        $this->alEntrevistador($entrevista, AvisoDeEntrevista::programadaParaEntrevistador($datos));
        $this->alCandidato($entrevista->postulacion?->candidato, AvisoDeEntrevista::programadaParaCandidato($datos));
    }

    public function handleEntrevistaCancelada(EntrevistaCancelada $evento): void
    {
        $entrevista = $this->cargar($evento->entrevista);
        $datos = DatosDeEntrevista::de($entrevista);

        $this->alEntrevistador($entrevista, AvisoDeEntrevista::canceladaParaEntrevistador($datos));
        $this->alCandidato($entrevista->postulacion?->candidato, AvisoDeEntrevista::canceladaParaCandidato($datos));
    }

    public function handleEntrevistaReprogramada(EntrevistaReprogramada $evento): void
    {
        $entrevista = $this->cargar($evento->entrevista);
        $datos = DatosDeEntrevista::de($entrevista);

        $this->alEntrevistador($entrevista, AvisoEntrevistaReprogramada::paraEntrevistador($datos, $evento->fechaAnterior));
        $this->alCandidato($entrevista->postulacion?->candidato, AvisoEntrevistaReprogramada::paraCandidato($datos, $evento->fechaAnterior));
    }

    public function handleCv(CvAnalizado $evento): void
    {
        if (! $evento->primeraVez) {
            return; // reanálisis de mantenimiento: sin avisos repetidos
        }

        $cv = $evento->cv->loadMissing(['subidoPor', 'candidato']);
        $quienLoSubio = $cv->subidoPor;
        $candidato = $cv->candidato;

        if ($quienLoSubio === null || $candidato === null) {
            return;
        }

        $competencias = count(array_filter(
            (array) ($cv->datos_estructurados['competencias'] ?? []),
            fn ($c) => is_array($c) && ! ($c['dudosa'] ?? false),
        ));

        $quienLoSubio->notify($quienLoSubio->id === $candidato->user_id
            ? AvisoCvAnalizado::paraPostulante($competencias, route('postulante.postulaciones'))
            : AvisoCvAnalizado::paraReclutador($candidato->nombre, $competencias, route('candidatos.show', $candidato)));
    }

    /** Postulación propia del candidato: aviso a quien creó la vacante y a la cuenta de empresa. */
    public function handleCandidatoSePostulo(CandidatoSePostulo $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing('candidato');
        // El postulante no tiene contexto de empresa: la vacante se lee sin ese filtro, por su id.
        $vacante = Vacante::sinFiltroDeEmpresa()->with('creador')->find($postulacion->vacante_id);

        if ($vacante === null) {
            return;
        }

        $aviso = AvisoNuevaPostulacion::de(
            (string) $postulacion->candidato?->nombre,
            $vacante->titulo,
            route('matching.show', $postulacion),
        );

        User::query()
            ->deEmpresa($vacante->empresa_id)
            ->role(Rol::Empresa->value)
            ->get()
            ->push($vacante->creador)
            ->filter(fn (?User $u) => $u !== null && $u->estaActivo() && $u->empresa_id === $vacante->empresa_id)
            ->unique('id')
            ->each(fn (User $u) => $u->notify($aviso));
    }

    /** Cambio de etapa: aviso diferido al candidato, salvo que lo haya hecho él mismo. */
    public function handleEtapa(PostulacionCambioDeEtapa $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing('candidato');

        if ($evento->actor !== null && $evento->actor->id === $postulacion->candidato?->user_id) {
            return;
        }

        AvisarEstadoDePostulacion::programar($postulacion);
    }

    /** Cierre de la vacante: aviso diferido a quienes seguían en el proceso (ni contratados ni descartados). */
    public function handleVacanteCerrada(VacanteCerrada $evento): void
    {
        Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $evento->vacante->id)
            ->whereNotIn('etapa', [EtapaPostulacion::Contratado, EtapaPostulacion::Descartado])
            ->get()
            ->each(fn (Postulacion $postulacion) => AvisarEstadoDePostulacion::programar($postulacion));
    }

    private function cargar(Entrevista $entrevista): Entrevista
    {
        return $entrevista->loadMissing(['entrevistador', 'postulacion.candidato.user']);
    }

    private function alEntrevistador(Entrevista $entrevista, Notification $aviso): void
    {
        $entrevistador = $entrevista->entrevistador;

        if ($entrevistador !== null && $entrevistador->id !== $this->usuarioActual()?->id) {
            $entrevistador->notify($aviso);
        }
    }

    private function alCandidato(?Candidato $candidato, Notification $aviso): void
    {
        $this->avisoAlCandidato->enviar($candidato, $aviso);
    }

    private function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
