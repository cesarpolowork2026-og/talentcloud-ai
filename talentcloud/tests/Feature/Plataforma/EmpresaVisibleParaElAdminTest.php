<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Livewire\Candidatos\Detalle as FichaCandidato;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Entrevistas\Listado as Entrevistas;
use App\Livewire\Evaluaciones\Listado as Evaluaciones;
use App\Livewire\Postulaciones\Detalle as FichaDePostulacion;
use App\Livewire\Seleccion\Central;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\CompetenciasDelCandidato;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use App\Support\OpcionesPorEmpresa;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Un candidato puede tener procesos en varias empresas. Para el admin SaaS
 * (vista global) la empresa organiza la información: procesos agrupados por
 * empresa, columna "Empresa" y selectores con un grupo por empresa. El
 * personal de una empresa solo ve lo suyo y lo ve como siempre.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
        $this->disenador = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);

        $this->sofia = Candidato::factory()->create(['nombre' => 'Sofía Nieto']);
        $this->ivan = Candidato::factory()->create(['nombre' => 'Iván Torres']);

        $this->deSofia = Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $this->sofia->id, 'created_at' => now()->subDays(2)]);
        // Iván está en procesos de las dos empresas.
        $this->ivanEnNimbus = Postulacion::factory()->paraVacante($this->backend)->enEtapa(EtapaPostulacion::Entrevista)
            ->create(['candidato_id' => $this->ivan->id, 'created_at' => now()->subDays(3)]);
        $this->ivanEnVela = Postulacion::factory()->paraVacante($this->disenador)
            ->create(['candidato_id' => $this->ivan->id, 'created_at' => now()->subHour()]);
    });

    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->admin = User::factory()->admin()->create();
});

// — Piezas —

it('agrupa las opciones por empresa solo en la vista global', function () {
    $vacantes = Vacante::sinFiltroDeEmpresa()->with('empresa')->orderBy('titulo')->get();
    $de = fn () => OpcionesPorEmpresa::de($vacantes, fn (Vacante $v) => $v, fn (Vacante $v) => $v->titulo);

    actuarComo($this->admin);
    expect($de())->toBe([
        'Nimbus Corp' => [$this->backend->id => 'Desarrollador Backend'],
        'Vela Studio' => [$this->disenador->id => 'Product Designer'],
    ]);

    actuarComo($this->marta);
    expect($de())->toBe([$this->backend->id => 'Desarrollador Backend', $this->disenador->id => 'Product Designer']);
});

it('pinta los grupos del selector como optgroup', function () {
    $html = Blade::render('<x-ui.select :options="$o" />', ['o' => ['Nimbus Corp' => [3 => 'Backend'], 7 => 'Suelta']]);

    expect($html)->toContain('<optgroup label="Nimbus Corp">')
        ->toContain('<option value="3" >Backend</option>')
        ->toContain('<option value="7" >Suelta</option>');
});

it('añade la empresa al nombre de la vacante solo en la vista global', function () {
    $vacante = $this->backend->load('empresa');

    actuarComo($this->marta);
    expect(Formato::vacante($vacante))->toBe('Desarrollador Backend');

    actuarComo($this->admin);
    expect(Formato::vacante($vacante))->toBe('Desarrollador Backend · Nimbus Corp');
});

// — Selectores agrupados —

it('agrupa por empresa los selectores del admin y deja planos los del reclutador', function () {
    // Los buscadores traen pocas coincidencias, con la empresa en la etiqueta cuando la vista abarca varias.
    actuarComo($this->admin);
    $vacantes = app()->call([Livewire::test(Central::class, ['vacante' => $this->backend])->instance(), 'buscarVacantes'], ['texto' => '']);
    expect(array_column($vacantes, 'etiqueta'))->toContain('Product Designer · Vela Studio');

    actuarComo($this->marta);
    $propias = app()->call([Livewire::test(Entrevistas::class)->instance(), 'buscarPostulaciones'], ['texto' => '']);
    expect(array_column($propias, 'etiqueta'))->toContain('Sofía Nieto — Desarrollador Backend')
        ->and(implode(' ', array_column($propias, 'etiqueta')))->not->toContain('Product Designer');
});

// — Candidatos —

it('muestra al admin los procesos de cada candidato agrupados por empresa', function () {
    actuarComo($this->admin);

    $html = Livewire::test(Candidatos::class)
        ->assertSee('2 procesos en 2 empresas')
        ->assertSee('Entrevista')
        ->assertSee('Postulado')
        ->assertDontSee('% match')
        ->html();

    // Iván: un bloque por empresa, en orden alfabético.
    expect(mb_strpos($html, 'Nimbus Corp'))->toBeLessThan(mb_strpos($html, 'Vela Studio'));
});

it('ordena por la postulación más reciente del candidato en la vista global', function () {
    actuarComo($this->admin);

    $html = Livewire::test(Candidatos::class)
        ->assertSee('Postulación más reciente')
        ->set('orden', 'reciente')
        ->call('alternarDireccion')
        ->html();

    // Iván (su último proceso fue hace una hora) antes que Sofía (hace dos días).
    expect(mb_strpos($html, 'Iván Torres'))->toBeLessThan(mb_strpos($html, 'Sofía Nieto'));
});

it('muestra los procesos agrupados en el diálogo del CV del admin', function () {
    actuarComo($this->admin);
    $componente = Livewire::test(Candidatos::class);
    $enTarjetas = substr_count($componente->html(), '2 procesos en 2 empresas');

    $componente->call('verDetalle', $this->ivan->id);

    expect(substr_count($componente->html(), '2 procesos en 2 empresas'))->toBe($enTarjetas + 1);
});

it('muestra al reclutador en Candidatos solo lo de su empresa, como siempre', function () {
    actuarComo($this->marta);

    Livewire::test(Candidatos::class)
        ->assertSee('Desarrollador Backend · ')
        ->assertDontSee('% match')
        ->assertSee('Postulación más reciente')
        ->assertDontSee('procesos en')
        ->assertDontSee('Product Designer');
});

it('no hace más consultas en Candidatos por tener más candidatos con procesos', function () {
    actuarComo($this->admin);
    $contar = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Candidatos::class);

        return count(DB::getQueryLog());
    };

    $contar(); // calentamiento: el primer renderizado carga roles y configuración
    $antes = $contar();
    app(TenantContext::class)->ejecutarGlobal(function () {
        Candidato::factory()->count(4)->create()->each(function (Candidato $c) {
            Postulacion::factory()->paraVacante($this->backend)->create(['candidato_id' => $c->id]);
            Postulacion::factory()->paraVacante($this->disenador)->create(['candidato_id' => $c->id]);
        });
    });

    expect($contar())->toBe($antes);
});

// — Ficha del candidato —

it('muestra al admin la ficha con los procesos agrupados por empresa', function () {
    actuarComo($this->admin);

    Livewire::test(FichaCandidato::class, ['candidato' => $this->ivan])
        ->assertSee('Procesos por empresa')
        ->assertSee('2 procesos en 2 empresas')
        ->assertSeeHtml('<h3 class="mb-2 text-15">Nimbus Corp</h3>')
        ->assertSeeHtml('<h3 class="mb-2 text-15">Vela Studio</h3>');

    actuarComo($this->marta);
    Livewire::test(FichaCandidato::class, ['candidato' => $this->ivan])
        ->assertSee('Postulaciones')
        ->assertDontSee('Procesos por empresa')
        ->assertDontSee('Product Designer');
});

it('muestra al admin las competencias anotadas por cada empresa, sin dejarle quitarlas', function () {
    $kotlin = Competencia::obtenerOCrear('Kotlin');
    $figma = Competencia::obtenerOCrear('Figma');
    $this->ivan->todasLasCompetencias()->attach($kotlin->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->nimbus->id]);
    $this->ivan->todasLasCompetencias()->attach($figma->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->vela->id]);

    actuarComo($this->admin);
    Livewire::test(FichaCandidato::class, ['candidato' => $this->ivan])
        ->assertSee('Anotadas por Nimbus Corp')
        ->assertSee('Anotadas por Vela Studio')
        ->call('quitarCompetencia', $kotlin->id, $this->nimbus->id)
        ->assertForbidden();

    $manuales = DB::table('candidato_competencia')->where('candidato_id', $this->ivan->id)->where('fuente', FuenteCompetencia::Manual->value)->pluck('empresa_id')->sort()->values()->all();
    expect($manuales)->toBe(collect([$this->nimbus->id, $this->vela->id])->sort()->values()->all());
});

it('no deja al admin anotar competencias: es una tarea de cada empresa', function () {
    actuarComo($this->admin);

    Livewire::test(FichaCandidato::class, ['candidato' => $this->ivan])
        ->set('nuevaCompetencia', 'Scrum')
        ->set('empresaCompetencia', (string) $this->vela->id)
        ->call('agregarCompetencia')
        ->assertForbidden();

    $sinProceso = app(TenantContext::class)->ejecutarGlobal(fn () => Empresa::factory()->create());
    expect(fn () => app(CompetenciasDelCandidato::class)->agregar($this->ivan, 'Go', $sinProceso->id))
        ->toThrow(OperacionNoPermitida::class, 'El candidato no tiene procesos en esa empresa.');
});

it('no deja al personal de una empresa anotar ni quitar para otra', function () {
    actuarComo($this->marta);
    $servicio = app(CompetenciasDelCandidato::class);

    expect(fn () => $servicio->agregar($this->ivan, 'Go', $this->vela->id))->toThrow(OperacionNoPermitida::class)
        ->and(fn () => $servicio->quitar($this->ivan, 1, $this->vela->id))->toThrow(OperacionNoPermitida::class);

    $servicio->agregar($this->ivan, 'Go');
    expect(DB::table('candidato_competencia')->where('candidato_id', $this->ivan->id)->value('empresa_id'))->toBe($this->nimbus->id);
});

// — Postulación, Entrevistas y Evaluaciones —

it('muestra al admin los otros procesos del candidato en la ficha de la postulación', function () {
    actuarComo($this->admin);
    Livewire::test(FichaDePostulacion::class, ['postulacion' => $this->ivanEnNimbus])
        ->assertSee('Otros procesos de este candidato')
        ->assertSee('Product Designer · Vela Studio')
        ->assertSeeHtml(route('postulaciones.show', $this->ivanEnVela));

    actuarComo($this->marta);
    Livewire::test(FichaDePostulacion::class, ['postulacion' => $this->ivanEnNimbus])->assertDontSee('Otros procesos');
});

it('añade la columna Empresa en Entrevistas y Evaluaciones solo para el admin', function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        Entrevista::factory()->create(['postulacion_id' => $this->ivanEnVela->id]);
        Evaluacion::factory()->create(['postulacion_id' => $this->ivanEnVela->id]);
        Entrevista::factory()->create(['postulacion_id' => $this->deSofia->id]);
    });

    actuarComo($this->admin);
    Livewire::test(Entrevistas::class)->assertSeeHtml('<th>Empresa</th>')->assertSeeHtml('<td>Vela Studio</td>');
    Livewire::test(Evaluaciones::class)->assertSeeHtml('<th>Empresa</th>')->assertSeeHtml('<td>Vela Studio</td>');

    actuarComo($this->marta);
    Livewire::test(Entrevistas::class)->assertDontSeeHtml('<th>Empresa</th>')->assertSeeHtml('<td>Desarrollador Backend</td>');
});
