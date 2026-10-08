<?php

use Illuminate\Support\Facades\DB;

/*
 * Configuración que no puede depender de que el entorno (local o GCP) la
 * declare: la zona horaria de la base de datos y el idioma de la aplicación.
 */

it('usa en la base de datos la misma zona horaria que la aplicación', function () {
    expect(DB::selectOne("select current_setting('TimeZone') as zona")->zona)->toBe(config('app.timezone'));
});

it('está en español aunque el entorno no declare APP_LOCALE', function () {
    $antes = [getenv('APP_LOCALE'), $_ENV['APP_LOCALE'] ?? null, $_SERVER['APP_LOCALE'] ?? null];
    putenv('APP_LOCALE');
    unset($_ENV['APP_LOCALE'], $_SERVER['APP_LOCALE']);

    try {
        // El valor por defecto de config/app.php, sin la variable de entorno.
        expect((require base_path('config/app.php'))['locale'])->toBe('es');
    } finally {
        if ($antes[0] !== false) {
            putenv('APP_LOCALE='.$antes[0]);
        }
        foreach (['_ENV' => $antes[1], '_SERVER' => $antes[2]] as $global => $valor) {
            if ($valor !== null) {
                $GLOBALS[$global]['APP_LOCALE'] = $valor;
            }
        }
    }
});
