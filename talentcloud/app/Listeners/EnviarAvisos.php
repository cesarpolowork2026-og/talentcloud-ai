<?php

namespace App\Listeners;

use App\DTO\DatosDeEntrevista;
use App\Enums\EtapaPostulacion;
use App\Enums\Rol;
use App\Events\CandidatoSePostulo;
use App\Events\ContrasenaCambiada;
use App\Events\CorreoCambiado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Events\EntrevistaReprogramada;
use App\Events\PagoFallido;
use App\Events\PlanEmpresaCambiado;
use App\Events\PlanPorVencer;
use App\Events\PlanSolicitado;
use App\Events\PlanVencido;
use App\Events\PostulacionCambioDeEtapa;
use App\Events\PostulacionRetirada;
use App\Events\VacanteCerrada;
use App\Jobs\AvisarEstadoDePostulacion;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCambioDePlan;
use App\Notifications\AvisoContrasenaCambiada;
use App\Notifications\AvisoCorreoCambiado;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoEntrevistaReprogramada;
use App\Notifications\AvisoNuevaPostulacion;
use App\Notifications\AvisoPagoFallido;
use App\Notifications\AvisoPostulacionRetirada;
use App\Notifications\AvisoSolicitudDePlan;
use App\Notifications\AvisoVencimientoDePlan;
use App\Services\Avisos\AvisoAlCandidato;
use App\Support\Formato;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * Notificaciones (campana y correo según preferencia): entrevistas programadas
 * o canceladas, postulaciones del candidato, cambios de plan y
 * cambios de estado o cierre del proceso. Los avisos al candidato salen
 * diferidos (AvisarEstadoDePostulacion) y nadie recibe aviso de lo que hizo él.
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
            route('postulaciones.show', $postulacion),
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
            route('seleccion.show', $vacante),
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

    /** El correo cambió desde «Mi perfil»: aviso de seguridad al correo anterior, que ya no es el de la cuenta. */
    public function handleCorreoCambiado(CorreoCambiado $evento): void
    {
        Notificaciones::route('mail', $evento->correoAnterior)
            ->notify(AvisoCorreoCambiado::de($evento->usuario->email, (string) config('talentcloud.soporte.correo')));
    }

    /** La contraseña se restableció con el enlace de recuperación: el mismo aviso. */
    public function handleContrasenaRestablecida(PasswordReset $evento): void
    {
        $evento->user->notify(AvisoContrasenaCambiada::ahora());
    }

    /** Cambio de plan (por el admin SaaS o por la pasarela de pago): aviso a la cuenta de empresa. */
    public function handlePlan(PlanEmpresaCambiado $evento): void
    {
        $empresa = $evento->empresa;
        $plan = $empresa->plan;
        $vence = $empresa->tieneVencimientoPropio() ? Formato::fechaCorta($empresa->plan_vence_at) : null;
        $aviso = $evento->anterior === $plan
            ? AvisoCambioDePlan::renovado($empresa->nombre, $plan->etiqueta(), $plan->descripcionDeLimites(), route('mi-empresa'), $vence)
            : AvisoCambioDePlan::de($empresa->nombre, $plan->etiqueta(), $plan->descripcionDeLimites(), route('mi-empresa'), $vence);

        $this->cuentasDeEmpresa($empresa)->each(fn (User $u) => $u->notify($aviso));
    }

    /** Al plan asignado a mano le quedan pocos días: la cuenta de empresa coordina la renovación. */
    public function handlePlanPorVencer(PlanPorVencer $evento): void
    {
        $empresa = $evento->empresa;
        $aviso = AvisoVencimientoDePlan::porVencer($empresa->nombre, $empresa->plan->etiqueta(), Formato::fechaCorta($empresa->plan_vence_at), $empresa->diasParaVencer(), route('mi-empresa'));

        $this->cuentasDeEmpresa($empresa)->each(fn (User $u) => $u->notify($aviso));
    }

    /** Venció el plan asignado a mano: ahora rige el Gratis. */
    public function handlePlanVencido(PlanVencido $evento): void
    {
        $empresa = $evento->empresa;
        $aviso = AvisoVencimientoDePlan::vencido($empresa->nombre, $empresa->plan->etiqueta(), Formato::fechaCorta($empresa->plan_vence_at), route('mi-empresa'));

        $this->cuentasDeEmpresa($empresa)->each(fn (User $u) => $u->notify($aviso));
    }

    /** La pasarela no pudo cobrar la renovación: la cuenta de empresa debe actualizar su tarjeta. */
    public function handlePagoFallido(PagoFallido $evento): void
    {
        $empresa = $evento->empresa;
        $aviso = AvisoPagoFallido::de($empresa->nombre, $empresa->plan->etiqueta(), route('mi-empresa'));

        $this->cuentasDeEmpresa($empresa)->each(fn (User $u) => $u->notify($aviso));
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

    /**
     * Las cuentas de empresa activas de $empresa (quienes gestionan su plan).
     *
     * @return Collection<int, User>
     */
    private function cuentasDeEmpresa(Empresa $empresa): Collection
    {
        return User::query()
            ->deEmpresa($empresa->id)
            ->role(Rol::Empresa->value)
            ->whereNull('desactivado_at')
            ->get();
    }

    private function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
