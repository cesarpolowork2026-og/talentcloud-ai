<?php

namespace App\Http\Controllers;

use App\Models\Entrevista;
use App\Support\Calendario\EventoIcs;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * «Agregar al calendario»: la entrevista como archivo .ics, solo para quien puede verla
 * (personal de selección de su empresa, o el admin SaaS).
 */
class DescargarCalendarioDeEntrevistaController extends Controller
{
    public function __invoke(Entrevista $entrevista): Response
    {
        Gate::authorize('view', $entrevista);

        $entrevista->load(['postulacion.candidato', 'postulacion.vacante.empresa', 'entrevistador']);

        return response(EventoIcs::de($entrevista), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.EventoIcs::nombreDeArchivo($entrevista).'"',
        ]);
    }
}
