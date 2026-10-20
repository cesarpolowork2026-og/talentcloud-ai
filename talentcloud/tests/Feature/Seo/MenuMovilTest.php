<?php

use App\Models\User;

/*
 * Por debajo de 1024 px la barra no tiene sitio para los enlaces: el menú se abre en un panel. Aquí se comprueba que el
 * marcado está y es accesible; el comportamiento con el puntero se comprueba en el navegador.
 */

it('las páginas públicas llevan un botón de menú accesible y un panel con los mismos enlaces', function (string $ruta) {
    $html = $this->get($ruta)->assertOk()->getContent();

    expect($html)->toContain('aria-controls="menu-publico"')
        ->and($html)->toContain('aria-label="Menú"')
        ->and($html)->toContain('id="menu-publico"');

    // El panel repite los enlaces de la barra de escritorio.
    preg_match('#<nav id="menu-publico".*?</nav>#s', $html, $panel);
    foreach ([route('empleos.index'), route('planes'), route('inicio').'#caracteristicas'] as $destino) {
        expect($panel[0])->toContain('href="'.$destino.'"');
    }
})->with(['/', '/empleos', '/planes', '/privacidad', '/terminos']);

it('el panel del menú ofrece crear empresa solo a quien no tiene sesión', function () {
    preg_match('#<nav id="menu-publico".*?</nav>#s', $this->get('/planes')->getContent(), $invitado);
    expect($invitado[0])->toContain('Crear empresa');

    $this->actingAs(User::factory()->create());
    preg_match('#<nav id="menu-publico".*?</nav>#s', $this->get('/planes')->getContent(), $conSesion);
    expect($conSesion[0])->not->toContain('Crear empresa');
});
