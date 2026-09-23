<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcesaEnLote;
use App\Enums\MotorMatching;
use App\Jobs\CalcularMatch;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Models\Postulacion;
use Illuminate\Console\Command;

/**
 * Vuelve a calcular el matching de las postulaciones. Con --solo-basicos, solo
 * las que se calcularon con el análisis básico porque el servicio de IA no
 * estaba disponible: se usa cuando el servicio vuelve.
 */
class RecalcularMatching extends Command
{
    use ProcesaEnLote;

    protected $signature = 'talentcloud:recalcular-matching
        {--solo-basicos : Solo las calculadas con el análisis básico (motor fake)}
        {--sync : Ejecutar ahora, sin pasar por la cola}';

    protected $description = 'Recalcula el matching de las postulaciones con el motor configurado';

    public function handle(MatchingEngineInterface $motor): int
    {
        if ($this->option('solo-basicos') && $motor instanceof FakeMatchingEngine) {
            $this->error('El motor configurado es el análisis básico (MATCHING_ENGINE=fake o IA deshabilitada): no mejoraría nada.');

            return self::FAILURE;
        }

        // Las postulaciones son de todas las empresas; cada cálculo corre con la suya (ConEmpresa).
        $postulaciones = Postulacion::sinFiltroDeEmpresa()
            ->when($this->option('solo-basicos'), fn ($q) => $q->where('match_motor', MotorMatching::Fake))
            ->orderBy('id')
            ->get(['id', 'empresa_id']);

        $fallos = $this->procesar('postulaciones', $postulaciones, fn (Postulacion $p) => $this->option('sync')
            ? CalcularMatch::dispatchSync($p->empresa_id, $p->id)
            : CalcularMatch::para($p));

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
