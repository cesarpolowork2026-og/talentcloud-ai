<?php

namespace App\Services\Cvs;

use App\Enums\EstadoProcesamientoCv;
use App\Jobs\ExtraerTextoCv;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda el CV en el disco privado (nombre aleatorio; el original solo se
 * usa al descargar) y encola la extracción de texto.
 */
class SubirCv
{
    public const MIMES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function ejecutar(Candidato $candidato, UploadedFile $archivo, ?User $subidoPor): Cv
    {
        $extension = strtolower($archivo->getClientOriginalExtension()) === 'docx' ? 'docx' : 'pdf';
        $disco = config('talentcloud.cvs.disco');
        $ruta = 'candidatos/'.$candidato->id.'/'.Str::uuid().'.'.$extension;

        Storage::disk($disco)->putFileAs(dirname($ruta), $archivo, basename($ruta));

        $cv = Cv::create([
            'candidato_id' => $candidato->id,
            'subido_por' => $subidoPor?->id,
            'disco' => $disco,
            'ruta' => $ruta,
            'nombre_original' => Str::limit($archivo->getClientOriginalName(), 200, ''),
            'mime' => self::MIMES[$extension],
            'tamano' => (int) $archivo->getSize(),
            'estado_procesamiento' => EstadoProcesamientoCv::Pendiente,
        ]);

        ExtraerTextoCv::dispatch($cv->id);

        return $cv;
    }

    /** "Reintentar análisis" del prototipo. */
    public function reintentar(Cv $cv): Cv
    {
        $cv->update(['estado_procesamiento' => EstadoProcesamientoCv::Pendiente, 'error' => null]);

        ExtraerTextoCv::dispatch($cv->id);

        return $cv;
    }
}
