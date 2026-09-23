<?php

namespace App\Jobs;

use App\Ia\RespuestaIaNoValida;
use App\Models\Vacante;
use App\Services\Vacantes\IndexarRequisitos;
use App\Tenancy\ConEmpresa;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Genera los embeddings de los requisitos de una vacante, con el contexto de su empresa. */
class GenerarEmbeddingsDeRequisitos implements ShouldBeUnique, ShouldQueue
{
    use ConEmpresa, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 60;

    public function __construct(public int $empresaId, public int $vacanteId)
    {
        $this->onQueue('ia');
        $this->afterCommit();
    }

    public static function para(Vacante $vacante): void
    {
        self::dispatch($vacante->empresa_id, $vacante->id);
    }

    public function uniqueId(): string
    {
        return (string) $this->vacanteId;
    }

    public function handle(IndexarRequisitos $indexar): void
    {
        $vacante = Vacante::find($this->vacanteId);

        if ($vacante === null) {
            return;
        }

        try {
            $indexar->ejecutar($vacante);
        } catch (RespuestaIaNoValida $e) {
            $this->fail($e);
        }
    }

    public function failed(?Throwable $error): void
    {
        Log::warning('No se pudieron generar los embeddings de los requisitos', [
            'vacante_id' => $this->vacanteId,
            'error' => $error?->getMessage(),
        ]);
    }
}
