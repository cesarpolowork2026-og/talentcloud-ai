<?php

use App\Enums\EstadoVacante;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Dashboard\Panel;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Vacantes\BuscadorDeVacantes;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * Una empresa puede tener miles de vacantes y postulaciones: los selectores no las envían todas al
 * navegador; los buscadores devuelven pocas coincidencias, escritas en el momento.
 */

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();

    foreach (range(1, 45) as $n) {
        Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => "Analista de Datos {$n}"]);
    }
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Ingeniero de Sistemas Árbol']);
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'Analista Cerrada']);

    actuarComo($this->marta);
});

it('devuelve como máximo 20 vacantes, las más recientes si no se escribe nada', function () {
    $resultado = app(BuscadorDeVacantes::class)->buscar('');

    expect($resultado)->toHaveCount(20)
        ->and(array_keys($resultado[0]))->toBe(['id', 'etiqueta', 'detalle']);
});

it('busca por título sin importar tildes, mayúsculas ni el orden de las palabras sueltas', function () {
    $buscador = app(BuscadorDeVacantes::class);

    expect(array_column($buscador->buscar('arbol'), 'etiqueta'))->toBe(['Ingeniero de Sistemas Árbol'])
        ->and(array_column($buscador->buscar('ÁRBOL'), 'etiqueta'))->toBe(['Ingeniero de Sistemas Árbol'])
        ->and($buscador->buscar('no existe'))->toBe([])
        // Los caracteres de comodín del SQL se buscan como texto, no como patrón.
        ->and($buscador->buscar('%'))->toBe([]);
});

it('puede acotar a las vacantes activas', function () {
    $buscador = app(BuscadorDeVacantes::class);

    expect($buscador->buscar('Analista Cerrada'))->toHaveCount(1)
        ->and($buscador->buscar('Analista Cerrada', soloActivas: true))->toBe([]);
});

it('no ofrece las vacantes de otra empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa(Empresa::factory()->create())->create(['titulo' => 'Vacante de otra empresa']));

    expect(app(BuscadorDeVacantes::class)->buscar('otra empresa'))->toBe([]);
});

it('el dashboard y «Postular a» usan buscadores y no incluyen todas las vacantes en la página', function () {
    // El buscador de vacantes del embudo es del dashboard de gerencia (la cuenta de empresa); el reclutador ve «Mi día».
    actuarComo(User::factory()->deEmpresa($this->nimbus)->empresa()->create());

    $html = Livewire::test(Panel::class)->html();

    expect($html)->toContain('role="combobox"')
        ->and($html)->not->toContain('Analista de Datos 30');

    $postular = Livewire::test(Candidatos::class)->html();
    expect($postular)->not->toContain('Analista de Datos 30');

    $dashboard = app()->call([Livewire::test(Panel::class)->instance(), 'buscarVacantes'], ['texto' => 'Datos 3']);
    expect(array_column($dashboard, 'etiqueta'))->toContain('Analista de Datos 30')->and(count($dashboard))->toBeLessThanOrEqual(20);

    $abiertas = app()->call([Livewire::test(Candidatos::class)->instance(), 'buscarVacantesAbiertas'], ['texto' => 'Cerrada']);
    expect($abiertas)->toBe([]); // «Postular a» solo ofrece vacantes abiertas
});
