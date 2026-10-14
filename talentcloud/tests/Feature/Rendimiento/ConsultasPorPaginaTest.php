<?php

use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Presupuesto de consultas por página, con los datos de demostración. En la
 * nube cada consulta viaja por la red hasta Cloud SQL: una consulta dentro de
 * un bucle (N+1) que aquí apenas se nota allí sumaría decenas de milisegundos.
 * Si una página supera su presupuesto, revisar la carga de relaciones antes de
 * subirlo. (Las cifras no incluyen la sesión: en los tests va en memoria.)
 */

/**
 * Consultas de la petición: la segunda visita, como en uso normal (la primera
 * llena cachés, como la de roles, y su cifra dependería del orden de las páginas).
 * $preparar (sesión y usuario) queda fuera de la cuenta.
 *
 * @return array{0: int, 1: array<string, int>} consultas y las que se repiten
 */
function consultasDeLaPagina(Closure $preparar, Closure $peticion): array
{
    $preparar();
    $peticion();

    $preparar();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $peticion();
    DB::disableQueryLog();

    $consultas = array_column(DB::getQueryLog(), 'query');
    $repetidas = array_filter(array_count_values($consultas), fn (int $n) => $n > 1);

    return [count($consultas), $repetidas];
}

it('no supera el presupuesto de consultas de cada página', function () {
    $this->seed(DatabaseSeeder::class);

    $ids = app(TenantContext::class)->ejecutarGlobal(fn () => [
        'backend' => Vacante::where('titulo', 'Desarrollador Backend')->value('id'),
        'abierta' => Vacante::where('titulo', 'Desarrollador Frontend')->value('id'),
    ]);

    $presupuestos = [
        'invitado' => ['/empleos' => 5, "/empleos/{$ids['abierta']}" => 5],
        'marta@nimbus.com' => [
            // Dashboard: atención, ahorro, plan y actividad suman consultas fijas (ninguna por fila).
            '/dashboard' => 21, '/vacantes' => 7, '/candidatos' => 12,
            // Selección lee además los requisitos de la vacante y sus competencias (2 consultas fijas, no una por tarjeta).
            "/seleccion/{$ids['backend']}" => 13, "/seleccion/{$ids['backend']}?vista=ranking" => 14,
            '/entrevistas' => 12, // + el resumen de la agenda (una lectura)
            '/evaluaciones' => 11,
        ],
        // Dashboard del admin: + la empresa de las vacantes de los avisos de atención (una lectura por lista).
        'admin@talentcloud.ai' => ['/dashboard' => 26, '/calidad-ia' => 9, '/candidatos' => 14],
        'marco@correo.com' => ['/postulante/postulaciones' => 10, "/postulante/vacantes/{$ids['abierta']}" => 11],
    ];

    $excedidas = [];
    foreach ($presupuestos as $quien => $paginas) {
        foreach ($paginas as $ruta => $maximo) {
            // Cada visita, como una petición real: sin la sesión de la anterior y con el usuario
            // recién leído (sus roles se cargan en la petición, no quedan de la visita anterior).
            $preparar = function () use ($quien) {
                $this->flushSession();
                $this->app['auth']->forgetGuards();
                if ($quien !== 'invitado') {
                    $this->actingAs(User::where('email', $quien)->firstOrFail());
                }
            };

            [$total, $repetidas] = consultasDeLaPagina($preparar, fn () => $this->get($ruta)->assertOk());

            if ($total > $maximo) {
                $excedidas[] = "{$quien} {$ruta}: {$total} consultas (máximo {$maximo}); repetidas: ".json_encode($repetidas);
            }
        }
    }

    expect($excedidas)->toBe([]);
});
