<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
 * Fija el marcado de los componentes x-ui.* frente a las clases del
 * design system Broadsheet que usa el prototipo.
 */

it('renderiza cada variante de botón con su clase completa', function (string $variant, string $clase) {
    $html = Blade::render('<x-ui.button :variant="$v">Guardar</x-ui.button>', ['v' => $variant]);

    expect($html)
        ->toContain('type="button"')
        ->toContain('btn '.$clase)
        ->toContain('Guardar');
})->with([
    ['primary', 'btn-primary'],
    ['secondary', 'btn-secondary'],
    ['ghost', 'btn-ghost'],
    ['danger', 'btn-danger'],
]);

it('renderiza el botón como enlace cuando recibe href', function () {
    $html = Blade::render('<x-ui.button variant="primary" href="/vacantes" size="sm">Ver</x-ui.button>');

    expect($html)
        ->toContain('<a href="/vacantes"')
        ->toContain('btn-primary')
        ->toContain('btn-sm')
        ->not->toContain('<button');
});

it('renderiza cada tono de etiqueta', function (string $tone, string $clase) {
    expect(Blade::render('<x-ui.tag :tone="$t">Abierta</x-ui.tag>', ['t' => $tone]))
        ->toContain('class="tag '.$clase.'"');
})->with([
    ['accent', 'tag-accent'],
    ['accent-2', 'tag-accent-2'],
    ['neutral', 'tag-neutral'],
    ['outline', 'tag-outline'],
]);

it('renderiza una tarjeta con kicker, título y elevación', function () {
    $html = Blade::render('<x-ui.card kicker="QA Engineer" title="Pablo Vega" elev="sm">Cuerpo</x-ui.card>');

    expect($html)
        ->toContain('card elev-sm')
        ->toContain('<div class="card-kicker">QA Engineer</div>')
        ->toContain('<div class="card-title">Pablo Vega</div>');
});

it('muestra el error de validación del campo', function () {
    $errores = (new ViewErrorBag)->put('default', new MessageBag(['email' => 'El campo correo electrónico es obligatorio.']));
    view()->share('errors', $errores);

    $html = Blade::render('<x-ui.field label="Correo" for="email" error="email"><x-ui.input id="email" error="email" /></x-ui.field>');

    expect($html)
        ->toContain('<label for="email">Correo</label>')
        ->toContain('aria-invalid="true"')
        ->toContain('class="field-error" role="alert">El campo correo electrónico es obligatorio.');
});

it('limita la barra de coincidencia entre 0 y 100', function () {
    expect(Blade::render('<x-ui.progress :value="140" />'))->toContain('aria-valuenow="100"')->toContain('width: 100%');
    expect(Blade::render('<x-ui.progress :value="-5" />'))->toContain('aria-valuenow="0"');
});

it('renderiza iconos Phosphor duotone ocultos a lectores de pantalla', function () {
    $html = Blade::render('<x-ui.icon name="bell" :size="20" class="text-neutral-700" />');

    expect($html)
        ->toContain('<svg')
        ->toContain('width="20"')
        ->toContain('aria-hidden="true"')
        ->toContain('text-neutral-700')
        ->toContain('opacity="0.2"');
});

it('renderiza la confirmación con la acción destructiva del prototipo', function () {
    $html = Blade::render('<x-ui.confirm name="descartar" title="Descartar a Pablo Vega" action-label="Descartar" action="descartar(5)">Texto</x-ui.confirm>');

    expect($html)
        ->toContain('class="dialog-backdrop"')
        ->toContain('role="dialog"')
        ->toContain('Descartar a Pablo Vega')
        ->toContain('btn btn-danger')
        ->toContain('wire:click="descartar(5)"')
        ->toContain('Cancelar');
});

it('no expone el catálogo de componentes fuera de local', function () {
    $this->get('/_ui')->assertNotFound();
});
