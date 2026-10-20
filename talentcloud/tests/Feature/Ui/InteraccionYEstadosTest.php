<?php

use App\Enums\EstadoVacante;
use App\Livewire\Empleos\Listado as ListadoEmpleos;
use App\Livewire\Evaluaciones\Listado as ListadoEvaluaciones;
use App\Livewire\Seleccion\Central;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
 * Hover, carga y vacíos: lo que se puede comprobar en el HTML. El comportamiento en un navegador (transiciones, la barra
 * de carga, el indicador del botón) lo cubre el recorrido de tests/e2e.
 */

beforeEach(function () {
    $this->empresa = Empresa::factory()->create();
    $this->reclutador = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista']);

    actuarComo($this->reclutador);
});

// — Carga —

it('la zona actualizable se atenúa y anuncia «Actualizando…» solo para lo que la actualiza', function () {
    $html = Blade::render('<x-ui.actualizable target="periodo">contenido</x-ui.actualizable>');

    expect($html)->toContain('wire:loading.class.delay="tc-actualizando"')
        ->and($html)->toContain('wire:target="periodo"')
        ->and($html)->toContain('role="status"')
        ->and($html)->toContain('Actualizando…')
        ->and($html)->toContain('contenido');

    // Por defecto sirve a las listas: búsqueda, filtro, orden y paginación.
    expect(Blade::render('<x-ui.actualizable>x</x-ui.actualizable>'))->toContain('busqueda,estado,orden,direccion,gotoPage,nextPage,previousPage,setPage');
});

it('las listas con datos, el dashboard y el tablero usan la zona actualizable', function () {
    Postulacion::factory()->paraVacante($this->vacante)->create(); // da una fila a Vacantes y a Candidatos

    foreach (['vacantes', 'candidatos'] as $pantalla) {
        $this->get("/{$pantalla}")->assertOk()->assertSee('wire:loading.class.delay="tc-actualizando"', false);
    }

    $this->get('/dashboard')->assertOk()->assertSee('wire:target="periodo"', false);
    $this->get('/seleccion/'.$this->vacante->id)->assertOk()->assertSee('wire:target="verMas,mover"', false);
});

// — Hover —

it('las tarjetas que se abren con un clic llevan el realce, y el arrastre solo las que se mueven', function () {
    Postulacion::factory()->paraVacante($this->vacante)->create();

    $html = Livewire::test(Central::class, ['vacante' => $this->vacante])->html();

    expect($html)->toContain('tarjeta-clicable')->and($html)->toContain('tarjeta-arrastrable');

    $indicador = Blade::render('<x-ui.stat label="Por revisar" :value="3" wire:click="x" />');
    expect($indicador)->toContain('tarjeta-clicable');

    // Un indicador que no se pulsa no parece pulsable.
    expect(Blade::render('<x-ui.stat label="Postulantes" :value="3" />'))->not->toContain('tarjeta-clicable');
});

// — Vacíos —

it('una columna del Kanban sin tarjetas dice qué es y, si se puede, qué hacer', function () {
    Postulacion::factory()->paraVacante($this->vacante)->create(); // en «Postulado»; las demás columnas, vacías

    $html = Livewire::test(Central::class, ['vacante' => $this->vacante])->html();

    expect(substr_count($html, 'columna-vacia'))->toBe(5) // todas menos «Postulado»
        ->and($html)->toContain('Arrastra una tarjeta aquí');
});

it('Evaluaciones sin ninguna evaluación invita a empezar; con un filtro sin resultados, no', function () {
    $pantalla = Livewire::test(ListadoEvaluaciones::class);

    $pantalla->assertSee('Aún no hay evaluaciones')
        ->assertSee('Se registran después de una entrevista realizada')
        ->assertSee('+ Nueva evaluación')
        ->assertDontSee('No se encontraron evaluaciones.');

    $pantalla->set('busqueda', 'nadie que se llame así')
        ->assertSee('No se encontraron evaluaciones.')
        ->assertDontSee('Aún no hay evaluaciones');
});

it('la bolsa pública distingue «no hay vacantes» de «no hay con esos filtros»', function () {
    $this->vacante->update(['estado' => EstadoVacante::Cerrada]);

    Livewire::test(ListadoEmpleos::class)
        ->assertSee('Todavía no hay vacantes abiertas. Vuelve pronto.')
        ->set('busqueda', 'zzz')
        ->assertSee('No hay vacantes abiertas con esos filtros.')
        ->assertDontSee('Vuelve pronto');
});

it('la ilustración nueva está registrada y pesa menos de 6 KB', function () {
    expect(Blade::render('<x-ilustracion nombre="sin-evaluaciones" />'))->toContain('<svg')->toContain('viewBox="0 0 240 160"')
        ->and(filesize(resource_path('views/components/ilustracion/formas/sin-evaluaciones.blade.php')))->toBeLessThan(6 * 1024);
});
