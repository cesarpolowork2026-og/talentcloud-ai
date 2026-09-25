<?php

namespace App\Services\Cuenta;

use App\Models\Actividad;
use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\User;
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
            // Candidato → CV, fragmentos, competencias y postulaciones → entrevistas, evaluaciones: en cascada.
            $usuario->delete();
        });

        // Los archivos, después de confirmar: si algo falló antes, no se pierde nada.
        $archivos->each(fn (Cv $cv) => $this->borrarArchivo($cv));
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
