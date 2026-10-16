<?php

namespace App\Services\Cuenta;

use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\User;
use App\Notifications\AvisoGeneral;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use App\Support\Formato;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Eliminar mi cuenta y mis datos": la cuenta, el perfil de candidato, sus CV
 * (filas y archivos), postulaciones, entrevistas, evaluaciones (en cascada),
 * sus notificaciones, y la actividad y los avisos del personal que lo nombran.
 */
class EliminarCuentaDePostulante
{
    public function ejecutar(User $usuario): void
    {
        $candidato = $usuario->candidato;
        $archivos = $candidato?->cvs()->get(['disco', 'ruta']) ?? collect();
        $avisos = $candidato !== null ? $this->avisosDeEntrevistasCanceladas($candidato) : [];

        DB::transaction(function () use ($usuario, $candidato) {
            if ($candidato !== null) {
                $postulaciones = Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $candidato->id)->pluck('id');

                Actividad::sinFiltroDeEmpresa()
                    ->where('sujeto_type', (new Postulacion)->getMorphClass())
                    ->whereIn('sujeto_id', $postulaciones)
                    ->delete();

                // Avisos de la campana del personal que lo nombran (llevan candidato_id en data).
                DatabaseNotification::query()
                    ->whereRaw("(data::jsonb ->> 'candidato_id') = ?", [(string) $candidato->id])
                    ->delete();
            }

            $usuario->notifications()->delete();
            // Se borran en cascada: del candidato, su CV, fragmentos, competencias y postulaciones; de estas, entrevistas y evaluaciones.
            $usuario->delete();
        });

        // Los archivos y los avisos, después de confirmar: si algo falló antes, no se pierde nada.
        $archivos->each(fn (Cv $cv) => $this->borrarArchivo($cv));
        foreach ($avisos as [$entrevistador, $texto]) {
            $entrevistador->notify(new AvisoGeneral($texto));
        }
    }

    /**
     * Sus entrevistas por realizar desaparecen con la cuenta: se avisa a cada
     * entrevistador para que no la espere. Sin el nombre ni ningún otro dato de
     * la persona, que pidió borrarlos.
     *
     * @return list<array{0: User, 1: string}>
     */
    private function avisosDeEntrevistasCanceladas(Candidato $candidato): array
    {
        $entrevistas = EntrevistasPorRealizar::consulta()
            ->whereHas('postulacion', fn ($q) => $q->withoutGlobalScopes()->where('candidato_id', $candidato->id))
            ->with(['entrevistador', 'postulacion' => fn ($q) => $q->withoutGlobalScopes()->with(['vacante' => fn ($v) => $v->withoutGlobalScopes()])])
            ->get();

        $avisos = [];

        foreach ($entrevistas as $entrevista) {
            $entrevistador = $entrevista->entrevistador;

            if ($entrevistador !== null && $entrevistador->estaActivo()) {
                $avisos[] = [$entrevistador, 'Se canceló tu entrevista del '.Formato::fechaHora($entrevista->inicia_at)
                    .' para '.$entrevista->postulacion?->vacante?->titulo.': la persona candidata eliminó su cuenta.'];
            }
        }

        return $avisos;
    }

    private function borrarArchivo(Cv $cv): void
    {
        try {
            Storage::disk($cv->disco)->delete($cv->ruta);
        } catch (Throwable $e) {
            Log::warning('No se pudo borrar el archivo de un CV eliminado', ['ruta' => $cv->ruta, 'error' => $e->getMessage()]);
        }
    }
}
