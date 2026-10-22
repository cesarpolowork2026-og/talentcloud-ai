<?php

use Illuminate\Support\Facades\Blade;

/*
 * Un diálogo con `aspa` (el de iniciar sesión) se cierra solo con la ✕ de arriba a la derecha o con Escape, no con un
 * clic en el fondo: un descuido no debe borrar lo que la persona estaba escribiendo. Los demás diálogos no cambian.
 */

it('con aspa no se cierra al hacer clic en el fondo y trae su botón de cierre', function () {
    $html = Blade::render('<x-ui.dialog name="x" title="Titulo" aspa>Cuerpo</x-ui.dialog>');

    expect($html)->not->toContain('x-on:click.self')
        ->and($html)->toContain('class="dialog-aspa"')
        ->and($html)->toContain('aria-label="Cerrar"')
        // Escape sigue cerrando (accesibilidad), y el botón cierra con la misma variable.
        ->and($html)->toContain('x-on:keydown.escape.window="open = false"')
        ->and($html)->toContain('x-on:click="open = false"');
});

it('la ✕ va después del contenido, para que el foco inicial caiga en el primer campo', function () {
    $html = Blade::render('<x-ui.dialog name="x" aspa><input id="primero"></x-ui.dialog>');

    expect(strpos($html, 'id="primero"'))->toBeLessThan(strpos($html, 'dialog-aspa'));
});

it('sin aspa el diálogo sigue cerrándose con un clic en el fondo', function () {
    $html = Blade::render('<x-ui.dialog name="x" title="Titulo">Cuerpo</x-ui.dialog>');

    expect($html)->toContain('x-on:click.self="open = false"')->and($html)->not->toContain('dialog-aspa');
});

it('el diálogo de inicio de sesión de la presentación lleva la ✕', function () {
    $this->get('/')->assertOk()->assertSee('class="dialog-aspa"', false)->assertSee('aria-label="Cerrar"', false);
    $this->get('/login')->assertOk()->assertSee('class="dialog-aspa"', false);
});
