<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

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

/** Ejecuta $prueba con variables de entorno temporales y las restaura al terminar. */
function conEntornoTemporal(array $variables, Closure $prueba): mixed
{
    $antes = [];

    foreach ($variables as $nombre => $valor) {
        $antes[$nombre] = [getenv($nombre), $_ENV[$nombre] ?? null, $_SERVER[$nombre] ?? null];
        putenv("{$nombre}={$valor}");
        $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
    }

    try {
        return $prueba();
    } finally {
        foreach ($antes as $nombre => [$env, $global, $servidor]) {
            $env === false ? putenv($nombre) : putenv("{$nombre}={$env}");
            $global === null ? $_ENV = array_diff_key($_ENV, [$nombre => 1]) : $_ENV[$nombre] = $global;
            $servidor === null ? $_SERVER = array_diff_key($_SERVER, [$nombre => 1]) : $_SERVER[$nombre] = $servidor;
        }
    }
}

it('marca la cookie de sesión como segura en producción aunque no se declare', function () {
    expect(conEntornoTemporal(['APP_ENV' => 'production'], fn () => (require base_path('config/session.php'))['secure']))->toBeTrue()
        ->and(conEntornoTemporal(['APP_ENV' => 'local'], fn () => (require base_path('config/session.php'))['secure']))->toBeFalse();
});

it('lee el entorno solo desde config/, para que funcione con la configuración en caché', function () {
    // Con "php artisan optimize" (producción), env() devuelve null fuera de config/.
    $archivos = collect(['app', 'routes', 'resources/views'])
        ->flatMap(fn (string $carpeta) => File::allFiles(base_path($carpeta)))
        ->map(fn (SplFileInfo $archivo) => $archivo->getPathname())
        ->push(base_path('bootstrap/app.php'));

    $conEnv = $archivos->filter(fn (string $ruta) => preg_match('/(?<![\w>:$])env\(/', (string) file_get_contents($ruta)) === 1);

    expect($conEnv->map(fn (string $ruta) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $ruta))->values()->all())->toBe([]);
});

it('reconoce el HTTPS del balanceador cuando se declara TRUSTED_PROXIES', function () {
    $esquema = function () {
        // El lector del .env recuerda lo que cargó y lo pisa al reiniciar la aplicación; si el .env
        // trae la línea TRUSTED_PROXIES= (como .env.example, que usa el CI) borraría el valor de la prueba.
        Env::enablePutenv();
        $this->refreshApplication();
        Route::get('/_prueba-esquema', fn () => request()->isSecure() ? 'https' : 'http');

        return $this->get('/_prueba-esquema', ['X-Forwarded-Proto' => 'https'])->getContent();
    };

    try {
        // Sin TRUSTED_PROXIES primero: trustProxies() guarda los proxies en una propiedad estática
        // del proceso (en producción, cada proceso arranca una sola vez).
        expect(conEntornoTemporal(['TRUSTED_PROXIES' => ''], $esquema))->toBe('http')
            ->and(conEntornoTemporal(['TRUSTED_PROXIES' => '*'], $esquema))->toBe('https');
    } finally {
        TrustProxies::flushState();
    }
});
