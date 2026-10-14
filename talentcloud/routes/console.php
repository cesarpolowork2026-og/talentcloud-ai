<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Mantenimiento: sin esto, estas tablas crecen para siempre. Ninguna tarea borra datos de negocio (postulaciones,
 * candidatos, actividad, notificaciones). En producción hace falta ejecutar `php artisan schedule:run` cada minuto
 * (Cloud Scheduler o cron).
 */
Schedule::command('auth:clear-resets')->dailyAt('03:00')->withoutOverlapping();       // tokens de restablecer contraseña vencidos
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:10')->withoutOverlapping(); // trabajos fallidos de hace más de 30 días
Schedule::command('talentcloud:limpiar-invitaciones')->dailyAt('03:20')->withoutOverlapping();
