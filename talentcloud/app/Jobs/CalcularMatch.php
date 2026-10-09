<?php

namespace App\Jobs;

use App\Enums\MotorMatching;
use App\Enums\ReservaDeCupoIa;
use App\Matching\ConstructorDeMatchInput;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Models\Postulacion;
use App\Services\Empresas\CupoDeIa;
use App\Tenancy\ConEmpresa;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Calcula el matching de una postulación y guarda el resultado explicado.
 * Corre en la cola "ia" con el contexto de la empresa de la postulación.
 * Único por postulación: varios cambios seguidos se calculan una vez.
 * Con el servicio de IA, cada postulación ocupa un lugar del cupo mensual del
 * plan (CupoDeIa); sin lugar, se evalúa con el análisis básico.
 */
class CalcularMatch implements ShouldBeUnique, ShouldQueue
{
    use ConEmpresa, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $uniqueFor = 60;

    public function __construct(public int $empresaId, public int $postulacionId)
    {
        $this->onQueue('ia');
        // Si se despacha dentro de una transacción, espera a que se confirme.
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->postulacionId;
    }

    public function handle(ConstructorDeMatchInput $constructor, MatchingEngineInterface $motor, FakeMatchingEngine $basico, CupoDeIa $cupo): void
    {
        $postulacion = Postulacion::find($this->postulacionId);

        if ($postulacion === null) {
            return;
        }

        $entrada = $constructor->desdePostulacion($postulacion);

        if ($entrada === null) {
            // Sin CV procesado o sin requisitos: no hay nada que comparar todavía.
            $postulacion->update(['match_score' => null, 'match_detalle' => null, 'match_motor' => null, 'match_calculado_at' => null]);

            return;
        }

        // Con la IA apagada (motor básico) no hay cupo que consumir.
        $reserva = $motor instanceof FakeMatchingEngine ? null : $cupo->reservar($postulacion);

        $resultado = ($reserva?->usaIa() === false ? $basico : $motor)->evaluar($entrada);

        // El servicio no respondió y se usó el análisis básico: el lugar no se consume.
        if ($reserva === ReservaDeCupoIa::Reservada && $resultado->motor === MotorMatching::Fake) {
            $cupo->liberar($postulacion);
        }

        $postulacion->forceFill(['ia_sin_cupo' => $reserva === ReservaDeCupoIa::Agotado])->update([
            'match_score' => $resultado->score,
            'match_detalle' => $resultado->aArreglo(),
            'match_motor' => $resultado->motor,
            'match_calculado_at' => now(),
        ]);
    }

    /** Programa el cálculo de una postulación con la empresa correcta. */
    public static function para(Postulacion $postulacion): void
    {
        self::dispatch($postulacion->empresa_id, $postulacion->id);
    }
}
