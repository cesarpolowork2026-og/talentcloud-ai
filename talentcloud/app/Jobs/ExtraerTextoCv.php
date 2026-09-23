<?php

namespace App\Jobs;

use App\Enums\EstadoProcesamientoCv;
use App\Events\CvProcesado;
use App\Models\Cv;
use App\Services\Cvs\Extraccion\ArchivoIlegible;
use App\Services\Cvs\Extraccion\LectorDeCv;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extrae el texto del CV y deja el resultado en el propio CV:
 * procesado (con texto) o error (con un mensaje para el usuario y el botón
 * "Reintentar análisis"). Los CV no pertenecen a una empresa, así que el
 * Job no necesita contexto de tenant.
 */
class ExtraerTextoCv implements ShouldQueue
{
    use Queueable;

    /** Un archivo ilegible no mejora reintentando: el reintento es manual. */
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $cvId) {}

    public function handle(LectorDeCv $lector): void
    {
        $cv = Cv::find($this->cvId);

        if ($cv === null) {
            return;
        }

        $cv->update(['estado_procesamiento' => EstadoProcesamientoCv::Procesando, 'error' => null]);

        try {
            $texto = $lector->leer(Storage::disk($cv->disco)->get($cv->ruta) ?? '', $cv->mime);

            $cv->update([
                'texto_extraido' => $texto,
                'estado_procesamiento' => EstadoProcesamientoCv::Procesado,
                'procesado_at' => now(),
                'error' => null,
            ]);
        } catch (ArchivoIlegible $e) {
            $cv->update(['estado_procesamiento' => EstadoProcesamientoCv::Error, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Fallo al procesar el CV', ['cv_id' => $cv->id, 'excepcion' => $e]);
            $cv->update(['estado_procesamiento' => EstadoProcesamientoCv::Error, 'error' => 'Ocurrió un error inesperado al leer el CV. Inténtalo de nuevo.']);
        }

        CvProcesado::dispatch($cv->refresh());
    }
}
