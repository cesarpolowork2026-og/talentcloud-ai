<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEntrevista;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Support\Calendario\EventoIcs;
use App\Tenancy\EmpresaScope;
use Illuminate\Http\Response;

/**
 * «Agregar al calendario» para el postulante: el .ics de una entrevista suya, pendiente o confirmada.
 * Es una entrevista de otra empresa, así que se lee por `EntrevistaRepository::delCandidatoPorId()`;
 * una ajena, cancelada o realizada responde 404 (no se revela si existe).
 */
class DescargarCalendarioDeMiEntrevistaController extends Controller
{
    public function __invoke(int $id, EntrevistaRepository $entrevistas): Response
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $candidato = $usuario->candidato;

        $entrevista = $candidato ? $entrevistas->delCandidatoPorId($candidato, $id) : null;

        abort_if($entrevista === null || ! in_array($entrevista->estado, [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada], true), 404);

        $postulacion = $candidato->postulacionesEnTodasLasEmpresas()
            ->with(['vacante' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)->with('empresa')])
            ->findOrFail($entrevista->postulacion_id);

        $ics = EventoIcs::paraCandidato($entrevista, (string) $postulacion->vacante?->titulo, (string) $postulacion->vacante?->empresa?->nombre);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="entrevista-'.$entrevista->inicia_at->format('Y-m-d').'.ics"',
        ]);
    }
}
