<?php

use App\Enums\Jornada;
use App\Livewire\Vacantes\Detalle;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->empresa = Empresa::factory()->create(['nombre' => 'Altura Café']);
    $this->marta = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->create(['titulo' => 'Barista']);
});

it('la empresa indica jornada y sueldo y se guardan', function () {
    actuarComo($this->marta);

    Livewire::test(Detalle::class, ['vacante' => $this->vacante])
        ->set('jornada', 'completa')
        ->set('sueldoMinimo', '1500')
        ->set('sueldoMaximo', '2200')
        ->call('guardarDatos')
        ->assertHasNoErrors();

    $fresca = $this->vacante->fresh();
    expect($fresca->jornada)->toBe(Jornada::Completa)
        ->and($fresca->sueldo_minimo)->toBe(1500)
        ->and($fresca->sueldo_maximo)->toBe(2200)
        ->and($fresca->sueldoEnTexto())->toBe('S/ 1 500 – 2 200 al mes');
});

it('todo es opcional: vaciar los campos los quita', function () {
    $this->vacante->update(['jornada' => 'parcial', 'sueldo_minimo' => 1200]);
    actuarComo($this->marta);

    Livewire::test(Detalle::class, ['vacante' => $this->vacante])
        ->set('jornada', '')->set('sueldoMinimo', '')->set('sueldoMaximo', '')
        ->call('guardarDatos')->assertHasNoErrors();

    $fresca = $this->vacante->fresh();
    expect($fresca->jornada)->toBeNull()->and($fresca->sueldo_minimo)->toBeNull()->and($fresca->sueldoEnTexto())->toBeNull();
});

it('valida el sueldo: números enteros, positivos y con el máximo no menor que el mínimo', function () {
    actuarComo($this->marta);

    Livewire::test(Detalle::class, ['vacante' => $this->vacante])
        ->set('sueldoMinimo', '2500')->set('sueldoMaximo', '2000')->call('guardarDatos')->assertHasErrors(['sueldoMaximo'])
        ->set('sueldoMinimo', 'mucho')->set('sueldoMaximo', '')->call('guardarDatos')->assertHasErrors(['sueldoMinimo'])
        ->set('sueldoMinimo', '0')->call('guardarDatos')->assertHasErrors(['sueldoMinimo'])
        ->set('sueldoMinimo', '1500.50')->call('guardarDatos')->assertHasErrors(['sueldoMinimo'])
        ->set('sueldoMinimo', '1500')->set('jornada', 'turno-noche')->call('guardarDatos')->assertHasErrors(['jornada']);

    expect($this->vacante->fresh()->sueldo_minimo)->toBeNull();
});

it('el sueldo se escribe según lo indicado: rango, desde, hasta o un solo monto', function () {
    $texto = fn (?int $min, ?int $max) => (new Vacante(['sueldo_minimo' => $min, 'sueldo_maximo' => $max]))->sueldoEnTexto();

    expect($texto(1500, 2200))->toBe('S/ 1 500 – 2 200 al mes')
        ->and($texto(1500, null))->toBe('Desde S/ 1 500 al mes')
        ->and($texto(null, 2200))->toBe('Hasta S/ 2 200 al mes')
        ->and($texto(1800, 1800))->toBe('S/ 1 800 al mes')
        ->and($texto(null, null))->toBeNull();
});

it('quien postula ve las condiciones en la ficha pública, y nada si la empresa no las indicó', function () {
    $this->get(route('empleos.show', $this->vacante->id))->assertOk()->assertDontSee('Condiciones');

    $this->vacante->update(['jornada' => 'parcial', 'sueldo_minimo' => 1200, 'sueldo_maximo' => 1500]);

    $this->get(route('empleos.show', $this->vacante->id))->assertOk()
        ->assertSee('Condiciones')->assertSee('Medio tiempo')->assertSee('S/ 1 200 – 1 500 al mes');
});

it('la oferta de empleo declara tipo de empleo y sueldo solo cuando existen', function () {
    $sin = jsonldDe($this->get(route('empleos.show', $this->vacante->id))->getContent())[0];
    expect($sin)->not->toHaveKeys(['employmentType', 'baseSalary']);

    $this->vacante->update(['jornada' => 'completa', 'sueldo_minimo' => 1500, 'sueldo_maximo' => 2200]);
    $con = jsonldDe($this->get(route('empleos.show', $this->vacante->id))->getContent())[0];

    expect($con['employmentType'])->toBe('FULL_TIME')
        ->and($con['baseSalary'])->toBe([
            '@type' => 'MonetaryAmount', 'currency' => 'PEN',
            'value' => ['@type' => 'QuantitativeValue', 'minValue' => 1500, 'maxValue' => 2200, 'unitText' => 'MONTH'],
        ]);

    $this->vacante->update(['jornada' => 'parcial', 'sueldo_minimo' => 1800, 'sueldo_maximo' => null]);
    $uno = jsonldDe($this->get(route('empleos.show', $this->vacante->id))->getContent())[0];
    expect($uno['employmentType'])->toBe('PART_TIME')
        ->and($uno['baseSalary']['value'])->toBe(['@type' => 'QuantitativeValue', 'value' => 1800, 'unitText' => 'MONTH']);
});

it('la base rechaza un sueldo incoherente aunque la aplicación no lo impida', function () {
    foreach ([['sueldo_minimo' => 3000, 'sueldo_maximo' => 2000], ['sueldo_minimo' => 0], ['sueldo_maximo' => 2000000], ['jornada' => 'nocturna']] as $cambio) {
        expect(fn () => DB::transaction(fn () => DB::table('vacantes')->where('id', $this->vacante->id)->update($cambio)))
            ->toThrow(QueryException::class);
    }
});
