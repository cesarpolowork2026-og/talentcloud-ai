<?php

namespace App\Console\Commands;

use App\Services\Empresas\AvisarVencimientosDePlan;
use Illuminate\Console\Command;

/** Tarea diaria: avisa a las empresas cuyo plan asignado a mano está por vencer o acaba de vencer. */
class AvisarVencimientosDePlanes extends Command
{
    protected $signature = 'talentcloud:avisar-vencimientos';

    protected $description = 'Avisa a las empresas cuyo plan vence pronto o venció hace pocos días (una sola vez por vencimiento)';

    public function handle(AvisarVencimientosDePlan $avisar): int
    {
        $this->info('Avisos de vencimiento enviados: '.$avisar->ejecutar().'.');

        return self::SUCCESS;
    }
}
