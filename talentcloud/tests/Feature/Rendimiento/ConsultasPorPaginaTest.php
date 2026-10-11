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

/** @return array{0: int, 1: array<string, int>} consultas y las que se repiten */
function consultasDeLaPagina(Closure $peticion): array
{
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = $consulta->sql;
    });

    $peticion();

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
            '/dashboard' => 17, '/vacantes' => 7, '/candidatos' => 12, "/vacantes/{$ids['backend']}/kanban" => 11,
            '/entrevistas' => 11, '/evaluaciones' => 11,
        ],
        'admin@talentcloud.ai' => ['/dashboard' => 23, '/calidad-ia' => 9, '/candidatos' => 14],
        'marco@correo.com' => ['/postulante/postulaciones' => 10, "/postulante/vacantes/{$ids['abierta']}" => 11],
    ];

    $excedidas = [];
    foreach ($presupuestos as $quien => $paginas) {
        foreach ($paginas as $ruta => $maximo) {
            // Cada página como una visita nueva: sin la sesión ni el usuario de la anterior.
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            if ($quien !== 'invitado') {
                $this->actingAs(User::where('email', $quien)->firstOrFail());
            }

            [$total, $repetidas] = consultasDeLaPagina(fn () => $this->get($ruta)->assertOk());

            if ($total > $maximo) {
                $excedidas[] = "{$quien} {$ruta}: {$total} consultas (máximo {$maximo}); repetidas: ".json_encode($repetidas);
            }
        }
    }

    expect($excedidas)->toBe([]);
});
