<?php

namespace App\Http\Controllers;

use App\Models\Cv;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga autorizada de un CV. Nunca hay URL pública del archivo: en disco
 * local se transmite; en un bucket, se redirige a una URL firmada de 5 minutos.
 */
class DescargarCvController extends Controller
{
    public function __invoke(Cv $cv): Response
    {
        Gate::authorize('view', $cv);

        $disco = Storage::disk($cv->disco);
        $esLocal = config("filesystems.disks.{$cv->disco}.driver") === 'local';

        if (! $esLocal && $disco->providesTemporaryUrls()) {
            return redirect()->away($disco->temporaryUrl($cv->ruta, now()->addMinutes(5), [
                'ResponseContentDisposition' => self::disposicion($cv->nombre_original),
            ]));
        }

        return $disco->download($cv->ruta, $cv->nombre_original);
    }

    /** "attachment" con el nombre original bien codificado (tildes, comillas) y un respaldo ASCII. */
    private static function disposicion(string $nombre): string
    {
        $ascii = Str::ascii($nombre);
        $respaldo = preg_replace('/[^A-Za-z0-9._ -]/', '_', $ascii) ?: 'cv';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, str_replace(['/', '\\'], '_', $nombre), $respaldo);
    }
}
