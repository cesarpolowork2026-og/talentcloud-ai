<?php

namespace App\Services\Postulaciones;

use App\Enums\MotorMatching;
use App\Jobs\CalcularMatch;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Models\Postulacion;
use Illuminate\Support\Collection;

/**
 * Recalcular el matching de las postulaciones de toda la plataforma. Lo usan
 * el comando talentcloud:recalcular-matching y "Calidad de la IA" del admin.
 * Cada cálculo corre después con la empresa de su postulación (ConEmpresa).
 */
class RecalculoDeMatching
{
    public function __construct(private readonly MatchingEngineInterface $motor) {}

    /** Rehacer los análisis básicos solo sirve si el motor configurado es el servicio de IA. */
    public function mejoraLosBasicos(): bool
    {
        return ! $this->motor instanceof FakeMatchingEngine;
    }

    /**
     * Postulaciones a recalcular (solo id y empresa_id), de todas las empresas.
     *
     * @return Collection<int, Postulacion>
     */
    public function postulaciones(bool $soloBasicos): Collection
    {
        return Postulacion::sinFiltroDeEmpresa()
            // Los básicos por cupo agotado no mejoran reintentando: los rehace un cambio de plan (ProgramarMatching).
            ->when($soloBasicos, fn ($q) => $q->where('match_motor', MotorMatching::Fake)->where('ia_sin_cupo', false))
            ->orderBy('id')
            ->get(['id', 'empresa_id']);
    }

    /** Encola el cálculo de los análisis básicos; devuelve cuántos. */
    public function encolarBasicos(): int
    {
        $postulaciones = $this->postulaciones(soloBasicos: true);
        $postulaciones->each(fn (Postulacion $p) => CalcularMatch::para($p));

        return $postulaciones->count();
    }
}
