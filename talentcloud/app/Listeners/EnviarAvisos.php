<?php

namespace App\Listeners;

use App\DTO\DatosDeEntrevista;
use App\Events\CvAnalizado;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaProgramada;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\User;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoDeEntrevista;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * Notificaciones (campana + correo según preferencia): entrevista programada o
 * cancelada al candidato y al entrevistador, y CV analizado a quien lo subió.
 * Nadie recibe aviso de lo que hizo él mismo.
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
