<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcesaEnLote;
use App\Jobs\CalcularMatch;
use App\Models\Postulacion;
use App\Services\Postulaciones\RecalculoDeMatching;
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

    public function handle(RecalculoDeMatching $recalculo): int
    {
        $soloBasicos = (bool) $this->option('solo-basicos');

        if ($soloBasicos && ! $recalculo->mejoraLosBasicos()) {
            $this->error('El motor configurado es el análisis básico (MATCHING_ENGINE=fake o IA deshabilitada): no mejoraría nada.');

            return self::FAILURE;
        }

        $fallos = $this->procesar('postulaciones', $recalculo->postulaciones($soloBasicos), fn (Postulacion $p) => $this->option('sync')
            ? CalcularMatch::dispatchSync($p->empresa_id, $p->id)
            : CalcularMatch::para($p));

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
