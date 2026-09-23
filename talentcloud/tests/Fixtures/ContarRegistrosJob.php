<?php

namespace Tests\Fixtures;

use App\Tenancy\ConEmpresa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Job de prueba: registra los títulos que ve con el contexto de su empresa. */
class ContarRegistrosJob implements ShouldQueue
{
    use ConEmpresa, Queueable;

    /** @var list<string> */
    public static array $vistos = [];

    public function __construct(public int $empresaId) {}

    public function handle(): void
    {
        self::$vistos = RegistroDeEmpresa::query()->orderBy('titulo')->pluck('titulo')->all();
    }
}
