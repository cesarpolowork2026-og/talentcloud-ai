<?php

use App\Console\Commands\LimpiarInvitacionesVencidas;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Invitacion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/*
 * Mantenimiento programado: sin él, tokens vencidos, trabajos fallidos e invitaciones caducadas se acumulan siempre.
 */

it('programa la limpieza diaria de tokens, trabajos fallidos e invitaciones vencidas', function () {
    $comandos = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

    expect($comandos)->toContain('auth:clear-resets')
        ->and($comandos)->toContain('queue:prune-failed')
        ->and($comandos)->toContain('talentcloud:limpiar-invitaciones')
        ->and($comandos)->toContain('talentcloud:avisar-vencimientos');
});

it('borra solo las invitaciones vencidas hace más de 30 días y nunca aceptadas', function () {
    Carbon::setTestNow('2026-10-13 12:00:00');
    $empresa = Empresa::factory()->create();
    $otra = Empresa::factory()->create();

    $nueva = function (Empresa $e, string $email, Carbon $expira, ?Carbon $aceptada = null) {
        $invitacion = new Invitacion(['email' => $email, 'rol' => Rol::Reclutador, 'token_hash' => hash('sha256', $email), 'expira_at' => $expira, 'aceptada_at' => $aceptada]);
        $invitacion->empresa_id = $e->id;
        $invitacion->save();

        return $invitacion;
    };

    $vencidaHaceMucho = $nueva($empresa, 'vieja@x.com', now()->subDays(LimpiarInvitacionesVencidas::DIAS_DE_GRACIA + 1));
    $vencidaDeOtra = $nueva($otra, 'vieja-otra@x.com', now()->subDays(90));
    $vencidaReciente = $nueva($empresa, 'reciente@x.com', now()->subDays(5));
    $pendiente = $nueva($empresa, 'pendiente@x.com', now()->addDays(3));
    $aceptada = $nueva($empresa, 'aceptada@x.com', now()->subDays(90), now()->subDays(100));

    $this->artisan('talentcloud:limpiar-invitaciones')->expectsOutput('Invitaciones vencidas borradas: 2.')->assertSuccessful();

    $quedan = Invitacion::sinFiltroDeEmpresa()->pluck('email')->sort()->values()->all();

    expect($quedan)->toBe(['aceptada@x.com', 'pendiente@x.com', 'reciente@x.com'])
        ->and(Invitacion::sinFiltroDeEmpresa()->whereKey([$vencidaHaceMucho->id, $vencidaDeOtra->id])->exists())->toBeFalse()
        ->and(Invitacion::sinFiltroDeEmpresa()->whereKey([$vencidaReciente->id, $pendiente->id, $aceptada->id])->count())->toBe(3);

    Carbon::setTestNow();
});
