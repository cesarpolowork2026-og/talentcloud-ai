<?php

namespace App\Tenancy;

/**
 * Para Jobs que operan sobre datos de una empresa. En la cola no hay usuario
 * autenticado: el Job recibe el empresa_id al despacharse y lo fija como
 * contexto al ejecutarse.
 *
 *     class CalcularMatch implements ShouldQueue
 *     {
 *         use ConEmpresa, Queueable;
 *
 *         public function __construct(public int $empresaId, public int $postulacionId) {}
 *     }
 *
 * Si el Job define su propio middleware(), debe incluir new EstablecerEmpresaEnJob.
 */
trait ConEmpresa
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EstablecerEmpresaEnJob];
    }
}
