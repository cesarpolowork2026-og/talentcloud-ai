<?php

namespace App\Listeners;

use App\DTO\DatosDeEntrevista;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Events\CandidatoSePostulo;
use App\Events\ContrasenaCambiada;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Events\EntrevistaReprogramada;
use App\Events\PlanEmpresaCambiado;
use App\Events\PlanSolicitado;
use App\Events\PostulacionCambioDeEtapa;
use App\Events\PostulacionRetirada;
use App\Events\VacanteCerrada;
use App\Jobs\AvisarEstadoDePostulacion;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCambioDePlan;
use App\Notifications\AvisoContrasenaCambiada;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoEntrevistaReprogramada;
use App\Notifications\AvisoNuevaPostulacion;
use App\Notifications\AvisoPostulacionRetirada;
use App\Notifications\AvisoSolicitudDePlan;
use App\Services\Avisos\AvisoAlCandidato;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notificaciones (campana + correo según preferencia): entrevista programada o
 * cancelada al candidato y al entrevistador, CV analizado a quien lo subió,
 * postulación propia del candidato a la empresa, cambio de plan a la cuenta de
 * empresa, y cambios de estado y cierre
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
            : AvisoCvAnalizado::paraReclutador($candidato->nombre, $competencias, route('candidatos.show', $candidato), $candidato->id));
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
            $postulacion->candidato_id,
        );

        $this->responsablesDe($vacante)->each(fn (User $u) => $u->notify($aviso));
    }

    /** El postulante retiró su postulación: aviso a quien creó la vacante y a la cuenta de empresa. */
    public function handleRetirada(PostulacionRetirada $evento): void
    {
        $postulacion = $evento->postulacion->loadMissing('candidato');
        $vacante = Vacante::sinFiltroDeEmpresa()->with('creador')->find($postulacion->vacante_id);

        if ($vacante === null) {
            return;
        }

        $aviso = AvisoPostulacionRetirada::de(
            (string) $postulacion->candidato?->nombre,
            $vacante->titulo,
            route('kanban.show', $vacante),
            $postulacion->candidato_id,
        );

        $this->responsablesDe($vacante)->each(fn (User $u) => $u->notify($aviso));
    }

    /**
     * Quien creó la vacante y la cuenta de empresa, activos y de esa empresa.
     *
     * @return Collection<int, User>
     */
    private function responsablesDe(Vacante $vacante): Collection
    {
        return User::query()
            ->deEmpresa($vacante->empresa_id)
            ->role(Rol::Empresa->value)
            ->get()
            ->push($vacante->creador)
            ->filter(fn (?User $u) => $u !== null && $u->estaActivo() && $u->empresa_id === $vacante->empresa_id)
            ->unique('id')
            ->values();
    }

    /** La contraseña cambió desde "Mi perfil": aviso de seguridad a la propia cuenta. */
    public function handleContrasenaCambiada(ContrasenaCambiada $evento): void
    {
        $evento->usuario->notify(AvisoContrasenaCambiada::ahora());
    }

    /** La contraseña se restableció con el enlace de recuperación: el mismo aviso. */
    public function handleContrasenaRestablecida(PasswordReset $evento): void
    {
        $evento->user->notify(AvisoContrasenaCambiada::ahora());
    }

    /** Cambio de plan por el admin SaaS: aviso a la cuenta de empresa. */
    public function handlePlan(PlanEmpresaCambiado $evento): void
    {
        $empresa = $evento->empresa;
        $aviso = AvisoCambioDePlan::de($empresa->nombre, $empresa->plan->etiqueta(), $empresa->plan->descripcionDeLimites(), route('mi-empresa'));

        User::query()
            ->deEmpresa($empresa->id)
            ->role(Rol::Empresa->value)
            ->whereNull('desactivado_at')
            ->get()
            ->each(fn (User $u) => $u->notify($aviso));
    }

    /** Solicitud de plan de una empresa: aviso a los admins SaaS activos, que la aplican. */
    public function handlePlanSolicitado(PlanSolicitado $evento): void
    {
        $empresa = $evento->empresa;
        $aviso = AvisoSolicitudDePlan::de($empresa->nombre, $empresa->etiquetaDelPlan(), $evento->plan->etiqueta(), $evento->solicitante->name, route('empresas.index'));

        User::query()
            ->role(Rol::Admin->value)
            ->whereNull('desactivado_at')
            ->get()
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
        // Nadie recibe aviso de lo que hizo él mismo (p. ej. entrevistas canceladas al retirarse).
        if ($candidato?->user_id !== null && $candidato->user_id === $this->usuarioActual()?->id) {
            return;
        }

        $this->avisoAlCandidato->enviar($candidato, $aviso);
    }

    private function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
