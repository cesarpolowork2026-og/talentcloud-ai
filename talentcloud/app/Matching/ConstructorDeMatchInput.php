<?php

namespace App\Matching;

use App\Enums\EstadoProcesamientoCv;
use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\Requisito;

/** Arma el MatchInput de una postulación: requisitos de su vacante y el CV con el que postuló. */
class ConstructorDeMatchInput
{
    /** null si todavía no hay CV con texto o la vacante no tiene requisitos. */
    public function desdePostulacion(Postulacion $postulacion): ?MatchInput
    {
        $postulacion->loadMissing(['vacante.requisitos.competencia', 'candidato']);

        $vacante = $postulacion->vacante;
        $cv = $this->cvDe($postulacion);

        if ($vacante === null || $cv === null || $vacante->requisitos->isEmpty()) {
            return null;
        }

        return new MatchInput(
            postulacionId: $postulacion->id,
            vacante: $vacante->titulo,
            descripcionVacante: $vacante->descripcion,
            requisitos: $vacante->requisitos->map(fn (Requisito $r) => new RequisitoInput(
                id: $r->id,
                competencia: $r->competencia->nombre,
                sinonimos: $r->competencia->sinonimos ?? [],
                tipo: $r->tipo,
                peso: $r->peso,
                aniosMinimos: $r->anios_minimos,
                descripcion: $r->descripcion,
            ))->values()->all(),
            cvId: $cv->id,
            textoCv: (string) $cv->texto_extraido,
        );
    }

    /** El CV de la postulación si ya está procesado; si no, el más reciente procesado del candidato. */
    private function cvDe(Postulacion $postulacion): ?Cv
    {
        $procesado = fn ($q) => $q->where('estado_procesamiento', EstadoProcesamientoCv::Procesado)->whereNotNull('texto_extraido');

        if ($postulacion->cv_id !== null) {
            $cv = Cv::whereKey($postulacion->cv_id)->where($procesado)->first();

            if ($cv !== null) {
                return $cv;
            }
        }

        return Cv::where('candidato_id', $postulacion->candidato_id)->where($procesado)->latest()->first();
    }
}
