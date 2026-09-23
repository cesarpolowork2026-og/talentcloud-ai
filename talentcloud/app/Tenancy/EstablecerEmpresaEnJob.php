<?php

namespace App\Tenancy;

use Closure;

/**
 * Middleware de Job: ejecuta el Job con el TenantContext de la empresa que
 * lleva en su payload (propiedad pública $empresaId, ver ConEmpresa).
 */
class EstablecerEmpresaEnJob
{
    public function handle(object $job, Closure $next): mixed
    {
        /** @var object{empresaId: int} $job */
        return app(TenantContext::class)->ejecutarPara($job->empresaId, fn () => $next($job));
    }
}
