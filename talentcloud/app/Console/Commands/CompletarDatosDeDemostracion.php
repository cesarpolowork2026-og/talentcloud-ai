<?php

namespace App\Console\Commands;

use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CondicionesDeDemostracion;
use Database\Seeders\Demo\ModalidadesDeEntrevistasDeDemostracion;
use Database\Seeders\Demo\PerfilesDeEmpresasDeDemostracion;
use Illuminate\Console\Command;

/**
 * Completa una base de demostración ya sembrada con lo que sus semillas añaden después: la jornada y el sueldo de las
 * vacantes, la descripción de cada empresa y la modalidad (con su dirección o teléfono) de algunas entrevistas futuras.
 * Sirve para ponerla al día sin volver a sembrar (y sin perder lo que se haya hecho en ella). Es idempotente y solo
 * rellena lo que está vacío.
 */
class CompletarDatosDeDemostracion extends Command
{
    protected $signature = 'talentcloud:completar-datos-demo';

    protected $description = 'Rellena la jornada, el sueldo, la descripción de empresas y el lugar de algunas entrevistas de la demostración (solo donde faltan)';

    public function handle(TenantContext $contexto): int
    {
        $contexto->ejecutarGlobal(function (): void {
            $this->info('Vacantes completadas: '.CondicionesDeDemostracion::aplicar().'.');
            $this->info('Empresas completadas: '.PerfilesDeEmpresasDeDemostracion::aplicar().'.');
            $this->info('Entrevistas con dirección o teléfono: '.ModalidadesDeEntrevistasDeDemostracion::aplicar().'.');
        });

        return self::SUCCESS;
    }
}
