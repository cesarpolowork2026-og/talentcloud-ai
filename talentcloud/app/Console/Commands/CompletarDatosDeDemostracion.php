<?php

namespace App\Console\Commands;

use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CondicionesDeDemostracion;
use Database\Seeders\Demo\PerfilesDeEmpresasDeDemostracion;
use Illuminate\Console\Command;

/**
 * Completa una base de demostración ya sembrada con lo que sus semillas añaden después: la jornada y el sueldo de las
 * vacantes y la descripción de cada empresa. Sirve para ponerla al día sin volver a sembrar (y sin perder lo que se haya
 * hecho en ella). Es idempotente y solo rellena lo que está vacío.
 */
class CompletarDatosDeDemostracion extends Command
{
    protected $signature = 'talentcloud:completar-datos-demo';

    protected $description = 'Rellena la jornada, el sueldo y la descripción de empresas de la demostración (solo donde faltan)';

    public function handle(TenantContext $contexto): int
    {
        $contexto->ejecutarGlobal(function (): void {
            $this->info('Vacantes completadas: '.CondicionesDeDemostracion::aplicar().'.');
            $this->info('Empresas completadas: '.PerfilesDeEmpresasDeDemostracion::aplicar().'.');
        });

        return self::SUCCESS;
    }
}
