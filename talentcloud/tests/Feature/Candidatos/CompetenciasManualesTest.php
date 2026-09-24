<?php

use App\Enums\FuenteCompetencia;
use App\Livewire\Candidatos\Detalle;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

/*
 * Las competencias manuales son anotaciones de cada empresa sobre un candidato
 * global: otra empresa donde también postuló no las ve ni las puede quitar.
 */

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create();
    $this->vela = Empresa::factory()->create();
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create();

    // Elena postuló en las dos empresas.
    $this->elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz']);
    app(TenantContext::class)->ejecutarGlobal(function () {
        foreach ([$this->nimbus, $this->vela] as $empresa) {
            Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($empresa)->create())->create(['candidato_id' => $this->elena->id]);
        }
    });
    $this->elena->todasLasCompetencias()->attach(Competencia::obtenerOCrear('Java')->id, ['fuente' => FuenteCompetencia::Extraccion->value]);
});

it('muestra las manuales solo a la empresa que las anotó', function () {
    actuarComo($this->marta);
    Livewire::test(Detalle::class, ['candidato' => $this->elena])
        ->set('nuevaCompetencia', 'Liderazgo')
        ->call('agregarCompetencia')
        ->assertHasNoErrors()
        ->assertSeeHtml('aria-label="Quitar Liderazgo"');

    actuarComo($this->clara);
    Livewire::test(Detalle::class, ['candidato' => $this->elena])
        ->assertDontSeeHtml('Quitar Liderazgo') // el catálogo (datalist) sí la incluye
        ->assertSee('Java · IA'); // las extraídas por la IA las ven todas
});

it('no deja quitar una competencia manual de otra empresa', function () {
    $liderazgo = Competencia::obtenerOCrear('Liderazgo');
    $this->elena->todasLasCompetencias()->attach($liderazgo->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->nimbus->id]);

    actuarComo($this->clara);
    Livewire::test(Detalle::class, ['candidato' => $this->elena])->call('quitarCompetencia', $liderazgo->id);

    expect($this->elena->todasLasCompetencias()->wherePivot('empresa_id', $this->nimbus->id)->pluck('nombre')->all())->toBe(['Liderazgo']);
});

it('permite que cada empresa anote la misma competencia', function () {
    foreach ([$this->marta, $this->clara] as $reclutador) {
        actuarComo($reclutador);
        Livewire::test(Detalle::class, ['candidato' => $this->elena])->set('nuevaCompetencia', 'Scrum')->call('agregarCompetencia')->assertHasNoErrors();
    }

    expect($this->elena->todasLasCompetencias()->wherePivot('fuente', FuenteCompetencia::Manual->value)->count())->toBe(2);
});

it('muestra una sola etiqueta si la competencia es manual y extraída', function () {
    $this->elena->todasLasCompetencias()->attach(Competencia::obtenerOCrear('Java')->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->nimbus->id]);

    actuarComo($this->marta);
    $html = Livewire::test(Detalle::class, ['candidato' => $this->elena])->html();

    expect(substr_count($html, 'wire:key="comp-'))->toBe(1)
        ->and($html)->toContain('aria-label="Quitar Java"');
});
