<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoProcesamientoCv;
use App\Enums\EstadoVacante;
use App\Livewire\Candidatos\Detalle;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoGeneral;
use App\Repositories\Contracts\CandidatoRepository;
use App\Repositories\Contracts\VacanteRepository;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
 * Cargas ligeras: lo que una pantalla no muestra no se lee. Cada comprobación fija una
 * decisión de la auditoría de carga del 15-10-2026 (ver AUDITORIA.md).
 */

beforeEach(function () {
    $this->empresa = Empresa::factory()->create();
    $this->marta = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->create();
    $this->candidato = Candidato::factory()->create();
    Postulacion::factory()->paraVacante($this->vacante)->create(['candidato_id' => $this->candidato->id]);
    actuarComo($this->marta);
});

it('el listado de candidatos trae solo un extracto del CV, no el texto completo', function () {
    Cv::factory()->procesado(str_repeat('Experiencia en backend. ', 2000))->create([
        'candidato_id' => $this->candidato->id,
        'datos_estructurados' => ['clave' => 'valor'],
    ]);

    $fila = app(CandidatoRepository::class)->consultaListado()->get()->firstWhere('id', $this->candidato->id);

    expect($fila->cvActual)->not->toBeNull()
        ->and(mb_strlen($fila->cvActual->texto_extraido))->toBe(1000)
        ->and($fila->cvActual->getAttributes())->not->toHaveKey('datos_estructurados')
        ->and($fila->cvActual->nombre_original)->toBe('CV.pdf');
});

it('esperarCv no repinta mientras el CV se analiza y repinta cuando termina', function () {
    $cv = Cv::factory()->create(['candidato_id' => $this->candidato->id, 'estado_procesamiento' => EstadoProcesamientoCv::Procesando]);

    // El texto de la tarjeta cambia al terminar: mientras se analiza, el sondeo no pinta nada nuevo.
    $componente = Livewire::test(Detalle::class, ['candidato' => $this->candidato]);

    $cv->update(['texto_extraido' => 'Texto nuevo del CV.']);
    $componente->call('esperarCv')->assertDontSee('Texto nuevo del CV.');

    $cv->update(['estado_procesamiento' => EstadoProcesamientoCv::Procesado, 'procesado_at' => now()]);
    $componente->call('esperarCv')->assertSee('Texto nuevo del CV.');
});

it('el catálogo de competencias se recuerda y se olvida al guardar o borrar', function () {
    Cache::flush();
    Competencia::factory()->create(['nombre' => 'Zig']);

    expect(Competencia::nombres())->toContain('Zig');

    // Sin pasar por la base: la segunda lectura sale de la caché.
    Competencia::query()->where('nombre', 'Zig')->toBase()->delete();
    expect(Competencia::nombres())->toContain('Zig');

    $nueva = Competencia::factory()->create(['nombre' => 'Nim']);
    expect(Competencia::nombres())->toContain('Nim')->not->toContain('Zig');

    $nueva->delete();
    expect(Competencia::nombres())->not->toContain('Nim');
});

it('suspender una empresa corta el acceso en la siguiente petición aunque su estado esté en caché', function () {
    $this->get('/dashboard')->assertOk();
    expect(Cache::has("empresa:{$this->empresa->id}:activa"))->toBeTrue();

    $this->empresa->update(['estado' => EstadoEmpresa::Suspendida]);

    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('la campana sabe si hay avisos sin leer con una sola consulta cuando alguno de los recientes lo está', function () {
    $this->marta->notify(new AvisoGeneral('Hola'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test('layout.navigation')->assertOk();
    $consultas = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'notifications'));

    expect($consultas)->toHaveCount(1);
});

it('las listas públicas no leen la descripción de la vacante, pero la ficha sí', function () {
    $abierta = Vacante::factory()->deEmpresa($this->empresa)->create([
        'estado' => EstadoVacante::Abierta,
        'publicada_at' => now(),
        'descripcion' => 'Texto largo de la descripción.',
    ]);

    $repo = app(VacanteRepository::class);
    $fila = $repo->publicadasParaListado()->get()->firstWhere('id', $abierta->id);

    expect($fila)->not->toBeNull()
        ->and($fila->getAttributes())->not->toHaveKey('descripcion')
        ->and($repo->buscarPublicada($abierta->id)->descripcion)->toBe('Texto largo de la descripción.');
});

it('la portada recuerda las vacantes abiertas y se actualiza al publicar o cerrar una', function () {
    Cache::flush();
    $primera = Vacante::factory()->deEmpresa($this->empresa)->create(['titulo' => 'Analista de Datos Uno', 'estado' => EstadoVacante::Abierta, 'publicada_at' => now()]);

    auth()->logout();
    $this->get('/')->assertOk()->assertSee('Analista de Datos Uno');
    expect(Cache::has('bolsa-publica:portada'))->toBeTrue();

    // Publicar otra: el modelo olvida la portada y la visita siguiente ya la incluye.
    Vacante::factory()->deEmpresa($this->empresa)->create(['titulo' => 'Analista de Datos Dos', 'estado' => EstadoVacante::Abierta, 'publicada_at' => now()]);
    $this->get('/')->assertSee('Analista de Datos Dos');

    // Cerrar la primera: deja de aparecer.
    $primera->update(['estado' => EstadoVacante::Cerrada]);
    $this->get('/')->assertDontSee('Analista de Datos Uno')->assertSee('Analista de Datos Dos');
});
