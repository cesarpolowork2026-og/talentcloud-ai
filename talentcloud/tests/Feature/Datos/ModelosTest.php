<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Blade;

it('normaliza competencias para evitar duplicados', function () {
    $postgres = Competencia::obtenerOCrear('PostgreSQL');

    expect(Competencia::obtenerOCrear('  postgresql ')->id)->toBe($postgres->id)
        ->and(Competencia::normalizar('Diseño  de   Producto'))->toBe('diseno de producto');
});

it('calcula el puntaje de una evaluación como promedio de sus criterios', function () {
    expect(Evaluacion::promediar([['nombre' => 'A', 'puntaje' => 9], ['nombre' => 'B', 'puntaje' => 8], ['nombre' => 'C', 'puntaje' => 9], ['nombre' => 'D', 'puntaje' => 8.4]]))->toBe(8.6)
        ->and(Evaluacion::promediar([]))->toBeNull();
});

it('usa los tonos de etiqueta de tagFor() del prototipo', function () {
    // accent: Abierta, Activa, Confirmada, Aprobado, Completada
    // neutral: Borrador, Pendiente, En revisión — outline: Cerrada, Cancelada, Suspendida
    expect(EstadoVacante::Abierta->tono())->toBe('accent')
        ->and(EstadoVacante::Borrador->tono())->toBe('neutral')
        ->and(EstadoVacante::EnRevision->tono())->toBe('neutral')
        ->and(EstadoVacante::Cerrada->tono())->toBe('outline')
        ->and(EstadoEmpresa::Activa->tono())->toBe('accent')
        ->and(EstadoEmpresa::Suspendida->tono())->toBe('outline')
        ->and(EstadoEntrevista::Confirmada->tono())->toBe('accent')
        ->and(EstadoEntrevista::Completada->tono())->toBe('accent')
        ->and(EstadoEntrevista::Pendiente->tono())->toBe('neutral')
        ->and(EstadoEntrevista::Cancelada->tono())->toBe('outline')
        ->and(EstadoEvaluacion::Aprobado->tono())->toBe('accent')
        ->and(EstadoEvaluacion::EnRevision->tono())->toBe('neutral');
});

it('renderiza un estado con su etiqueta y tono', function () {
    expect(Blade::render('<x-ui.estado :valor="$e" />', ['e' => EstadoVacante::EnRevision]))
        ->toContain('tag tag-neutral')
        ->toContain('En revisión');
});

it('ofrece las opciones de un estado en el orden declarado', function () {
    expect(EstadoVacante::opciones())->toBe([
        'borrador' => 'Borrador',
        'abierta' => 'Abierta',
        'en_revision' => 'En revisión',
        'cerrada' => 'Cerrada',
    ]);
});

it('hereda la empresa de la vacante en requisitos y postulaciones', function () {
    $vacante = Vacante::factory()->create();

    expect(Requisito::factory()->create(['vacante_id' => $vacante->id])->empresa_id)->toBe($vacante->empresa_id)
        ->and(Postulacion::factory()->paraVacante($vacante)->create()->empresa_id)->toBe($vacante->empresa_id);
});

it('aísla vacantes, requisitos y postulaciones entre empresas', function () {
    $nimbus = Empresa::factory()->create();
    $vela = Empresa::factory()->create();
    $vacanteVela = Vacante::factory()->deEmpresa($vela)->create();
    Requisito::factory()->create(['vacante_id' => $vacanteVela->id]);
    Postulacion::factory()->paraVacante($vacanteVela)->create();

    app(TenantContext::class)->ejecutarPara($nimbus->id, function () use ($vacanteVela) {
        expect(Vacante::find($vacanteVela->id))->toBeNull()
            ->and(Requisito::count())->toBe(0)
            ->and(Postulacion::count())->toBe(0);
    });

    app(TenantContext::class)->ejecutarPara($vela->id, fn () => expect(Vacante::count())->toBe(1));
});
