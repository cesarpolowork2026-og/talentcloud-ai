<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Recorre elementos con barra de progreso: un fallo no detiene el lote, se
 * reporta (log) y se cuenta. Requiere la opción --sync en el comando.
 *
 * @mixin Command
 */
trait ProcesaEnLote
{
    /**
     * @template T
     *
     * @param  Collection<int, T>  $elementos
     * @param  callable(T): mixed  $accion
     * @return int fallos
     */
    protected function procesar(string $nombre, Collection $elementos, callable $accion): int
    {
        $this->info(($this->option('sync') ? 'Procesando ' : 'Encolando ').$elementos->count()." {$nombre}…");
        $fallos = 0;

        $this->withProgressBar($elementos, function ($elemento) use ($accion, &$fallos) {
            try {
                $accion($elemento);
            } catch (Throwable $e) {
                $fallos++;
                report($e);
            }
        });
        $this->newLine();

        if ($fallos > 0) {
            $this->warn("{$fallos} {$nombre} no se pudieron procesar (ver el log).");
        }

        return $fallos;
    }
}
