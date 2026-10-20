<?php

use App\Livewire\Empleos\Listado;
use App\Models\Empresa;
use App\Models\Vacante;
use Livewire\Livewire;

beforeEach(function () {
    $empresa = Empresa::factory()->create();
    Vacante::factory()->count(30)->deEmpresa($empresa)->create();
});

it('pagina /empleos con enlaces reales que un buscador puede seguir', function () {
    $html = $this->get('/empleos')->assertOk()->getContent();

    expect($html)->toContain('<a href="'.route('empleos.index').'?page=2"')
        ->and($html)->toContain('rel="next"')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->not->toContain('wire:click="nextPage');
});

it('la página 2 responde con otras vacantes y enlaza a la anterior', function () {
    $html = $this->get('/empleos?page=2')->assertOk()->getContent();

    expect($html)->toContain('rel="prev"')
        ->and($html)->toContain('<a href="'.route('empleos.index').'?page=3"');
});

it('el clic sigue sin recargar la página: cambia de página con Livewire', function () {
    Livewire::test(Listado::class)->call('gotoPage', 2)->assertSee('aria-current="page"', escape: false);
});
