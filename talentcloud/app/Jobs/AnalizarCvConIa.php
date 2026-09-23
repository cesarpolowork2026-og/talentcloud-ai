<?php

namespace App\Jobs;

use App\Ia\RespuestaIaNoValida;
use App\Models\Cv;
use App\Services\Cvs\AnalizarCv;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analiza un CV con el servicio de IA después de extraer su texto. El CV y los
 * candidatos son globales: no necesita contexto de empresa.
 *
 * Si el servicio no responde se reintenta; si al final falla, el CV sigue
 * siendo utilizable (tiene su texto) y el matching usa el análisis básico.
 */
class AnalizarCvConIa implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 300;

    public function __construct(public int $cvId)
    {
        $this->onQueue('ia');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->cvId;
    }

    public function handle(AnalizarCv $analizar): void
    {
        $cv = Cv::find($this->cvId);

        if ($cv === null) {
            return;
        }

        try {
            $analizar->ejecutar($cv);
        } catch (RespuestaIaNoValida $e) {
            // Un error de contrato o de clave no se arregla reintentando.
            $this->fail($e);
        }
    }

    public function failed(?Throwable $error): void
    {
        Log::warning('No se pudo analizar el CV con IA', ['cv_id' => $this->cvId, 'error' => $error?->getMessage()]);
    }
}
