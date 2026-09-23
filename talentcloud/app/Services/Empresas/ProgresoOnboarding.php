<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepository;

/**
 * Checklist de bienvenida del prototipo: perfil, primera vacante, invitar al
 * equipo. Cada paso se marca solo cuando la acción ocurre de verdad.
 */
class ProgresoOnboarding
{
    public function __construct(private readonly EmpresaRepository $empresas) {}

    public function marcar(Empresa $empresa, string $paso): void
    {
        if (! in_array($paso, Empresa::PASOS_ONBOARDING, true)) {
            return;
        }

        $this->empresas->marcarPasoOnboarding($empresa, $paso);
    }

    public function estaCompletado(Empresa $empresa, string $paso): bool
    {
        return $this->estado($empresa)[$paso] ?? false;
    }

    public function completados(Empresa $empresa): int
    {
        return count(array_filter($this->estado($empresa)));
    }

    public function terminado(Empresa $empresa): bool
    {
        return $this->completados($empresa) === count(Empresa::PASOS_ONBOARDING);
    }

    /**
     * @return array<string, bool> paso => completado, en el orden del checklist
     */
    public function estado(Empresa $empresa): array
    {
        $guardado = $empresa->onboarding ?? [];

        $estado = [];
        foreach (Empresa::PASOS_ONBOARDING as $paso) {
            $estado[$paso] = (bool) ($guardado[$paso] ?? false);
        }

        return $estado;
    }
}
