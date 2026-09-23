<?php

namespace App\Tenancy;

use Closure;

/**
 * Empresa (tenant) sobre la que opera la petición o el Job en curso.
 *
 * - En peticiones web lo fija el middleware EstablecerContextoTenant a partir
 *   del usuario autenticado.
 * - En Jobs lo fija el middleware de Job EstablecerEmpresaEnJob con el
 *   empresa_id que el Job lleva en su payload.
 * - El admin SaaS opera en modo global: ve todas las empresas.
 *
 * Sin contexto, las consultas de modelos con BelongsToEmpresa salen vacías
 * (falla cerrado) y no se pueden crear registros de empresa.
 *
 * Se registra como "scoped": se reinicia en cada petición y en cada Job.
 */
class TenantContext
{
    private ?int $empresaId = null;

    private bool $global = false;

    public function establecer(int $empresaId): void
    {
        $this->empresaId = $empresaId;
        $this->global = false;
    }

    /** Modo del admin SaaS: sin filtro de empresa. */
    public function establecerGlobal(): void
    {
        $this->empresaId = null;
        $this->global = true;
    }

    public function limpiar(): void
    {
        $this->empresaId = null;
        $this->global = false;
    }

    public function id(): ?int
    {
        return $this->empresaId;
    }

    public function esGlobal(): bool
    {
        return $this->global;
    }

    public function tieneEmpresa(): bool
    {
        return $this->empresaId !== null;
    }

    /**
     * Ejecuta el callback con el contexto de una empresa y restaura el anterior.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function ejecutarPara(int $empresaId, Closure $callback): mixed
    {
        [$anteriorId, $anteriorGlobal] = [$this->empresaId, $this->global];

        $this->establecer($empresaId);

        try {
            return $callback();
        } finally {
            [$this->empresaId, $this->global] = [$anteriorId, $anteriorGlobal];
        }
    }

    /**
     * Ejecuta el callback sin filtro de empresa (tareas de plataforma, seeders).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function ejecutarGlobal(Closure $callback): mixed
    {
        [$anteriorId, $anteriorGlobal] = [$this->empresaId, $this->global];

        $this->establecerGlobal();

        try {
            return $callback();
        } finally {
            [$this->empresaId, $this->global] = [$anteriorId, $anteriorGlobal];
        }
    }
}
