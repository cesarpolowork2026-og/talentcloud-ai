<?php

namespace App\Services\Entrevistas;

use App\Enums\EstadoEntrevista;
use App\Events\EntrevistaCancelada;
use App\Events\EntrevistaConfirmada;
use App\Events\EntrevistaRealizada;
use App\Models\Entrevista;
use App\Services\Vacantes\OperacionNoPermitida;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Pendiente, Confirmada y Completada; se cancela desde Pendiente o Confirmada.
 * Las transiciones válidas las define EstadoEntrevista.
 */
class CambiarEstadoEntrevista
{
    public function confirmar(Entrevista $entrevista): Entrevista
    {
        $confirmada = $this->cambiar($entrevista, EstadoEntrevista::Confirmada, fn (Entrevista $e) => $e->estado->puedeConfirmarse());

        EntrevistaConfirmada::dispatch($confirmada);

        return $confirmada;
    }

    public function completar(Entrevista $entrevista): Entrevista
    {
        $realizada = $this->cambiar($entrevista, EstadoEntrevista::Completada, function (Entrevista $e) {
            if ($e->estado->puedeCompletarse() && $e->inicia_at->isFuture()) {
                throw new OperacionNoPermitida('Solo se puede dar por completada una entrevista que ya empezó.');
            }

            return $e->estado->puedeCompletarse();
        });

        EntrevistaRealizada::dispatch($realizada);

        return $realizada;
    }

    public function cancelar(Entrevista $entrevista): Entrevista
    {
        $cancelada = $this->cambiar($entrevista, EstadoEntrevista::Cancelada, fn (Entrevista $e) => $e->estado->puedeCancelarse());

        EntrevistaCancelada::dispatch($cancelada);

        return $cancelada;
    }

    /**
     * @param  Closure(Entrevista): bool  $permitido
     */
    private function cambiar(Entrevista $entrevista, EstadoEntrevista $nuevo, Closure $permitido): Entrevista
    {
        // Se relee bloqueada: dos cancelaciones simultáneas no notifican dos veces.
        return DB::transaction(function () use ($entrevista, $nuevo, $permitido) {
            $actual = Entrevista::whereKey($entrevista->id)->lockForUpdate()->firstOrFail();

            if (! $permitido($actual)) {
                throw new OperacionNoPermitida('La entrevista ya está '.mb_strtolower($actual->estado->etiqueta()).'.');
            }

            $actual->update(['estado' => $nuevo]);

            return $actual;
        });
    }
}
