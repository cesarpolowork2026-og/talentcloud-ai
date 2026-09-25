<?php

namespace App\Listeners;

use App\DTO\DatosDeEntrevista;
use App\Enums\Rol;
use App\Events\CandidatoSePostulo;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoNuevaPostulacion;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * Notificaciones (campana + correo según preferencia): entrevista programada o
 * cancelada al candidato y al entrevistador, CV analizado a quien lo subió y
 * postulación propia del candidato a la empresa. Nadie recibe aviso de lo que
 * hizo él mismo.
 */
class EnviarAvisos
{
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

    /** Con cuenta de postulante: campana y correo según su preferencia. Sin cuenta: solo correo. */
    private function alCandidato(?Candidato $candidato, Notification $aviso): void
    {
        if ($candidato?->user !== null) {
            $candidato->user->notify($aviso);
        } elseif (filled($candidato?->email)) {
            Notificaciones::route('mail', $candidato->email)->notify($aviso);
        }
    }

    private function usuarioActual(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
