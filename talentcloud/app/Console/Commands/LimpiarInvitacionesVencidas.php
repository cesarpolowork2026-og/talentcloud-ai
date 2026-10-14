<?php

namespace App\Console\Commands;

use App\Models\Invitacion;
use Illuminate\Console\Command;

/**
 * Borra las invitaciones que vencieron hace tiempo sin aceptarse: ya no sirven (el enlace caducó) y solo ocupan
 * espacio. No toca las pendientes ni las aceptadas (son el rastro de quién entró y cuándo).
 */
class LimpiarInvitacionesVencidas extends Command
{
    /** Días que se conserva una invitación vencida antes de borrarla (por si alguien pregunta por qué no le funcionó). */
    public const DIAS_DE_GRACIA = 30;

    protected $signature = 'talentcloud:limpiar-invitaciones';

    protected $description = 'Borra las invitaciones vencidas hace más de 30 días y nunca aceptadas';

    public function handle(): int
    {
        // Tarea de plataforma: recorre todas las empresas a la vez.
        $borradas = Invitacion::sinFiltroDeEmpresa()
            ->whereNull('aceptada_at')
            ->where('expira_at', '<', now()->subDays(self::DIAS_DE_GRACIA))
            ->delete();

        $this->info("Invitaciones vencidas borradas: {$borradas}.");

        return self::SUCCESS;
    }
}
