<?php

it('guarda las predicciones del análisis básico sobre el conjunto de evaluación', function () {
    $salida = tempnam(sys_get_temp_dir(), 'predicciones');

    $this->artisan('talentcloud:evaluar-motor-basico', ['--salida' => $salida])
        ->expectsOutputToContain('145 predicciones')
        ->assertSuccessful();

    $casos = json_decode((string) file_get_contents($salida), true)['casos'];
    $javaDeElena = collect($casos)->firstWhere(fn (array $c) => $c['cv'] === 'elena' && $c['requisito_id'] === 1);

    expect($casos)->toHaveCount(145)
        ->and($javaDeElena['nivel'])->toBe('coincide');

    unlink($salida);
});
