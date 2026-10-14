<?php

/*
 * Un trabajo no puede durar más de lo que la cola espera antes de darlo por perdido (`retry_after`): si no, otro
 * worker lo toma mientras el primero aún corre y el trabajo se ejecuta dos veces.
 */

it('el retry_after de la cola supera el timeout de cada trabajo', function () {
    $retryAfter = (int) config('queue.connections.database.retry_after');
    $mayor = 0;

    foreach (glob(app_path('Jobs/*.php')) as $archivo) {
        $clase = 'App\\Jobs\\'.basename($archivo, '.php');
        $reflexion = new ReflectionClass($clase);

        if ($reflexion->hasProperty('timeout')) {
            $timeout = (int) $reflexion->getProperty('timeout')->getDefaultValue();
            expect($retryAfter)->toBeGreaterThan($timeout, "{$clase} dura hasta {$timeout} s y la cola reintenta a los {$retryAfter} s");
            $mayor = max($mayor, $timeout);
        }
    }

    expect($mayor)->toBeGreaterThan(0); // si ningún trabajo declara timeout, esta prueba ya no vigila nada
});

it('las llamadas al servicio de IA terminan antes de que la cola reintente', function () {
    // Un trabajo sin `timeout` propio usa el del worker (60 s por defecto en `queue:work`); la llamada HTTP debe caber.
    expect((int) config('talentcloud.ia.timeout'))->toBeLessThan(60)
        ->and((int) config('queue.connections.database.retry_after'))->toBeGreaterThan((int) config('talentcloud.ia.timeout') * 3);
});
