<?php

namespace App\Services\Vacantes;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Events\VacanteCerrada;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Entrevistas\EntrevistasPorRealizar;
use Illuminate\Support\Facades\DB;

/**
 * Transiciones de estado de una vacante: Borrador, Abierta, En revisión y
 * Cerrada. Publicar un borrador es lo único que suma una vacante activa, y ahí
 * se aplica el límite del plan (Abierta y En revisión no cambian el número y una
 * cerrada no se reabre).
 */
class CambiarEstadoVacante
{
    public function __construct(
        private readonly VacanteRepository $vacantes,
        private readonly LimitesDelPlan $limites,
    ) {}

    public function ejecutar(Vacante $vacante, EstadoVacante $destino): Vacante
    {
        if (! $vacante->estado->puedePasarA($destino)) {
            throw new OperacionNoPermitida(
                'Una vacante '.mb_strtolower($vacante->estado->etiqueta()).' no puede pasar a '.mb_strtolower($destino->etiqueta()).'.'
            );
        }

        if ($destino === EstadoVacante::Abierta && ! Requisito::where('vacante_id', $vacante->id)->exists()) {
            throw new OperacionNoPermitida('Agrega al menos un requisito antes de publicar la vacante.');
        }

        if ($destino === EstadoVacante::Cerrada) {
            $this->verificarSinEntrevistasPorRealizar($vacante);
            $this->verificarSinOfertasAbiertas($vacante);
        }

        $datos = ['estado' => $destino];

        if ($destino === EstadoVacante::Abierta && $vacante->publicada_at === null) {
            $datos['publicada_at'] = now();
        }

        if ($destino === EstadoVacante::Cerrada) {
            $datos['cerrada_at'] = now();
        }

        $publicaBorrador = $vacante->estado === EstadoVacante::Borrador && $destino === EstadoVacante::Abierta;

        $vacante = DB::transaction(function () use ($vacante, $datos, $publicaBorrador): Vacante {
            if ($publicaBorrador) {
                $this->limites->verificarVacanteActiva($vacante->empresa_id);
            }

            return $this->vacantes->actualizar($vacante, $datos);
        });

        if ($destino === EstadoVacante::Cerrada) {
            VacanteCerrada::dispatch($vacante);
        }

        return $vacante;
    }

    /**
     * Al cerrar, a quienes siguen en el proceso se les avisa del cierre: no
     * pueden tener a la vez una entrevista programada. Primero se cancelan.
     */
    private function verificarSinEntrevistasPorRealizar(Vacante $vacante): void
    {
        $nombres = EntrevistasPorRealizar::consulta()
            ->whereHas('postulacion', fn ($q) => $q->withoutGlobalScopes()->where('vacante_id', $vacante->id))
            ->with(['postulacion' => fn ($q) => $q->withoutGlobalScopes()->with('candidato')])
            ->get()
            ->map(fn ($e) => $e->postulacion?->candidato?->nombre);

        if ($nombres->isNotEmpty()) {
            throw new OperacionNoPermitida(
                'La vacante tiene '.EntrevistasPorRealizar::texto($nombres->count()).' por realizar ('.$nombres->unique()->join(', ', ' y ').'). '
                .EntrevistasPorRealizar::segun($nombres->count(), 'Cancélala', 'Cancélalas').' en «Entrevistas» antes de cerrarla.'
            );
        }
    }

    /**
     * Quien está en Oferta sigue viendo «Entrevista» y, al cerrar, recibiría «El proceso se cerró. Gracias por
     * postular» mientras la empresa le ofrece el puesto. Antes de cerrar se decide: Contratado o Descartado.
     */
    private function verificarSinOfertasAbiertas(Vacante $vacante): void
    {
        $nombres = Postulacion::query()->withoutGlobalScopes()
            ->where('vacante_id', $vacante->id)
            ->where('etapa', EtapaPostulacion::Oferta)
            ->whereNull('retirada_at')
            ->with(['candidato' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->map(fn (Postulacion $p) => $p->candidato?->nombre);

        if ($nombres->isNotEmpty()) {
            throw new OperacionNoPermitida(
                $nombres->join(', ', ' y ').($nombres->count() === 1 ? ' está' : ' están').' en «Oferta». '
                .'Antes de cerrar la vacante, en «Selección» '.($nombres->count() === 1 ? 'muévela' : 'muévelas').' a «Contratado» o «Descartado».'
            );
        }
    }
}
