<?php

use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\MotorMatching;
use App\Enums\NivelCoincidencia;
use App\Enums\TipoRequisito;
use App\Events\PostulacionCambioDeEtapa;
use App\Jobs\CalcularMatch;
use App\Livewire\Seleccion\Central;
use App\Matching\CumplimientoDeRequisitos;
use App\Matching\MatchResult;
use App\Matching\RequisitoMatch;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->create(['titulo' => 'Desarrollador Backend']);

    $crear = fn (string $nombre, EtapaPostulacion $etapa, int $orden, ?int $match = null) => Postulacion::factory()
        ->paraVacante($this->backend)
        ->create([
            'candidato_id' => Candidato::factory()->create(['nombre' => $nombre, 'titular' => 'Backend'])->id,
            'etapa' => $etapa,
            'orden' => $orden,
            'match_score' => $match,
        ]);

    $this->elena = $crear('Elena Ruiz', EtapaPostulacion::Postulado, 0, 88);
    $this->marco = $crear('Marco Díaz', EtapaPostulacion::Postulado, 1, 64);
    $this->sofia = $crear('Sofía Nieto', EtapaPostulacion::EnRevision, 0, 91);
    $this->pablo = $crear('Pablo Vega', EtapaPostulacion::Descartado, 0, 42);

    actuarComo($this->marta);
});

function columna(Vacante $vacante, EtapaPostulacion $etapa): array
{
    return Postulacion::where('vacante_id', $vacante->id)
        ->where('etapa', $etapa)
        ->orderBy('orden')
        ->with('candidato')
        ->get()
        ->map(fn (Postulacion $p) => $p->candidato->nombre.':'.$p->orden)
        ->all();
}

it('muestra el tablero con las seis columnas y el match de cada tarjeta', function () {
    $this->get(route('kanban.show', $this->backend))
        ->assertOk()
        ->assertSee('Selección · Desarrollador Backend')
        ->assertSee('Nimbus Corp — 3 postulaciones activas.')
        ->assertSeeInOrder(['Postulado', 'En revisión', 'Entrevista', 'Oferta', 'Contratado', 'Descartado'])
        ->assertSee('88%')
        ->assertSee('Descartar a Elena Ruiz', false);
});

it('abre desde el menú la vacante activa más reciente', function () {
    $this->get('/seleccion')->assertOk()->assertSee('Selección · Desarrollador Backend');
    $this->get('/kanban')->assertOk()->assertSee('Selección · Desarrollador Backend');
});

it('muestra un estado vacío si no hay vacantes activas', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    $this->get('/kanban')->assertOk()
        ->assertSee('Aún no hay nada que seleccionar')
        ->assertSee('Cuando tengas una vacante abierta')
        ->assertSeeHtml('class="btn ')
        ->assertDontSee('No hay vacantes abiertas para mostrar su selección.');
});

it('mueve entre columnas y renumera ambas sin huecos', function () {
    Event::fake([PostulacionCambioDeEtapa::class]);

    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('mover', $this->elena->id, 'en_revision', 0)
        ->assertHasNoErrors();

    expect(columna($this->backend, EtapaPostulacion::Postulado))->toBe(['Marco Díaz:0'])
        ->and(columna($this->backend, EtapaPostulacion::EnRevision))->toBe(['Elena Ruiz:0', 'Sofía Nieto:1'])
        ->and($this->elena->fresh()->etapa_cambiada_at)->not->toBeNull();

    Event::assertDispatched(PostulacionCambioDeEtapa::class, fn ($e) => $e->origen === EtapaPostulacion::Postulado
        && $e->destino === EtapaPostulacion::EnRevision
        && $e->actor->is($this->marta));
});

it('reordena dentro de la misma columna sin disparar el evento', function () {
    Event::fake([PostulacionCambioDeEtapa::class]);

    app(MoverPostulacion::class)->ejecutar($this->marco, EtapaPostulacion::Postulado, 0, $this->marta);

    expect(columna($this->backend, EtapaPostulacion::Postulado))->toBe(['Marco Díaz:0', 'Elena Ruiz:1']);
    Event::assertNotDispatched(PostulacionCambioDeEtapa::class);
});

it('ajusta posiciones fuera de rango al final de la columna', function () {
    app(MoverPostulacion::class)->ejecutar($this->elena, EtapaPostulacion::EnRevision, 99, $this->marta);

    expect(columna($this->backend, EtapaPostulacion::EnRevision))->toBe(['Sofía Nieto:0', 'Elena Ruiz:1']);
});

it('descarta con la confirmación del prototipo, al final de Descartado', function () {
    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('confirmarDescarte', $this->elena->id)
        ->assertSet('mostrarDescarte', true)
        ->assertSee('Descartar a Elena Ruiz')
        ->assertSee('El candidato se moverá fuera del proceso de esta vacante. Esta acción no elimina su perfil ni su CV.')
        ->call('descartar');

    expect(columna($this->backend, EtapaPostulacion::Descartado))->toBe(['Pablo Vega:0', 'Elena Ruiz:1'])
        ->and(Candidato::where('nombre', 'Elena Ruiz')->exists())->toBeTrue();
});

it('mueve desde el diálogo con "Mover a…" (alternativa al arrastre)', function () {
    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('abrir', $this->marco->id)
        ->assertSee('Columna: Postulado.')
        ->assertSee('64% de coincidencia con la vacante.')
        ->set('etapaDestino', 'oferta')
        ->call('moverA')
        ->assertSet('mostrarDetalle', false);

    expect($this->marco->fresh()->etapa)->toBe(EtapaPostulacion::Oferta);
});

it('no permite mover en una vacante cerrada', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->assertSee('La vacante está cerrada: la selección es de solo lectura.')
        ->call('mover', $this->elena->id, 'oferta', 0)
        ->assertHasErrors(['tablero']);

    expect($this->elena->fresh()->etapa)->toBe(EtapaPostulacion::Postulado);
});

it('rechaza etapas inexistentes', function () {
    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('mover', $this->elena->id, 'aprobado-por-ia', 0)
        ->assertNotFound();
});

it('no deja mover postulaciones de otra vacante ni de otra empresa', function () {
    // Datos de otra empresa: desde el contexto de Nimbus no se pueden crear (lo impide BelongsToEmpresa).
    $ajena = app(TenantContext::class)->ejecutarGlobal(
        fn () => Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa(Empresa::factory()->create())->create())->create()
    );
    $otraPropia = Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($this->nimbus)->create())->create();

    Livewire::test(Central::class, ['vacante' => $this->backend])->call('mover', $ajena->id, 'oferta', 0)->assertNotFound();
    Livewire::test(Central::class, ['vacante' => $this->backend])->call('mover', $otraPropia->id, 'oferta', 0)->assertNotFound();

    $this->get(route('kanban.show', $ajena->vacante_id))->assertNotFound();
});

it('niega el Kanban al postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())->get('/kanban')->assertForbidden();
});

it('bloquea movimientos en una vacante cerrada también desde el servicio', function () {
    $this->backend->update(['estado' => EstadoVacante::Cerrada]);

    app(MoverPostulacion::class)->ejecutar($this->elena->fresh(), EtapaPostulacion::Oferta, 0, $this->marta);
})->throws(OperacionNoPermitida::class);

// — Descartar con entrevistas por realizar —

it('no descarta a quien tiene entrevistas por realizar y explica qué hacer', function () {
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'estado' => EstadoEntrevista::Confirmada]);

    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->call('confirmarDescarte', $this->sofia->id)
        ->call('descartar')
        ->assertHasErrors('tablero')
        ->assertSee('Sofía Nieto tiene 1 entrevista por realizar. Cancélala en «Entrevistas» y vuelve a descartar.');

    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::EnRevision);
});

it('descarta cuando sus entrevistas ya están realizadas o canceladas', function () {
    foreach ([EstadoEntrevista::Completada, EstadoEntrevista::Cancelada] as $estado) {
        Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => now()->subDay(), 'estado' => $estado]);
    }

    app(MoverPostulacion::class)->descartar($this->sofia, $this->marta);

    expect($this->sofia->fresh()->etapa)->toBe(EtapaPostulacion::Descartado);
});

it('ofrece "Evaluar" solo a quien ya tuvo una entrevista realizada', function () {
    $tablero = Livewire::test(Central::class, ['vacante' => $this->backend]);

    $tablero->call('abrir', $this->sofia->id)->assertDontSee('Evaluar');

    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => now()->subDay(), 'estado' => EstadoEntrevista::Completada]);

    $tablero->call('abrir', $this->sofia->id)->assertSee('Evaluar');
});

it('abre la tarjeta con el teclado: el nombre es un botón', function () {
    Livewire::test(Central::class, ['vacante' => $this->backend])
        ->assertSeeHtml('wire:click.stop="abrir('.$this->sofia->id.')"');
});

it('ordena el ranking por coincidencia y oculta a los descartados salvo que se pida', function () {
    $pantalla = Livewire::test(Central::class, ['vacante' => $this->backend])->set('vista', 'ranking');
    $html = $pantalla->html();

    $posicion = fn (string $nombre) => mb_strpos($html, $nombre);

    expect($posicion('Sofía Nieto'))->toBeLessThan($posicion('Elena Ruiz'))
        ->and($posicion('Elena Ruiz'))->toBeLessThan($posicion('Marco Díaz'))
        ->and($posicion('Pablo Vega'))->toBeFalse();

    $pantalla->set('verDescartados', true)->assertSee('Pablo Vega');
});

it('el ranking solo ordena: no mueve ni descarta a nadie', function () {
    $antes = Postulacion::where('vacante_id', $this->backend->id)->pluck('etapa', 'id')->all();

    Livewire::test(Central::class, ['vacante' => $this->backend])->set('vista', 'ranking')->set('soloObligatorios', true);

    expect(Postulacion::where('vacante_id', $this->backend->id)->pluck('etapa', 'id')->all())->toEqual($antes);
});

it('muestra qué requisitos de la vacante cubre cada CV y filtra por los obligatorios', function () {
    $java = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'tipo' => TipoRequisito::Obligatorio]);
    $docker = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Docker')->id, 'tipo' => TipoRequisito::Deseable]);

    $detalle = fn (NivelCoincidencia $nivelJava, NivelCoincidencia $nivelDocker) => (new MatchResult(80, [
        new RequisitoMatch($java->id, 'Java', 80, $nivelJava, 'Java 5 años', 'ok'),
        new RequisitoMatch($docker->id, 'Docker', 50, $nivelDocker, null, 'ok'),
    ], MotorMatching::Fake))->aArreglo();

    // Como CalcularMatch: guarda el matching y su resumen indexado (lo que usan el tablero y los filtros).
    $guardar = function (Postulacion $postulacion, array $detalle) {
        $postulacion->update(['match_detalle' => $detalle, 'match_calculado_at' => now(), ...CumplimientoDeRequisitos::de($detalle, $this->backend->requisitos()->with('competencia')->get())->columnas()]);
    };
    $guardar($this->elena, $detalle(NivelCoincidencia::Coincide, NivelCoincidencia::NoEncontrado));
    $guardar($this->sofia, $detalle(NivelCoincidencia::NoEncontrado, NivelCoincidencia::Coincide));

    $pantalla = Livewire::test(Central::class, ['vacante' => $this->backend])
        ->assertSee('1 de 2 requisitos')
        ->assertSee('falta 1 obligatorio');

    // Solo Elena cubre el obligatorio (Java): Sofía y quienes no tienen análisis quedan fuera.
    $pantalla->set('soloObligatorios', true)
        ->assertSee('Elena Ruiz')
        ->assertDontSee('Sofía Nieto')
        ->assertDontSee('Marco Díaz')
        ->assertSee('postulaciones ocultas por el filtro');
});

it('cuenta como «sin calcular» el requisito que se agregó después del cálculo', function () {
    $java = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'tipo' => TipoRequisito::Obligatorio]);
    $detalle = (new MatchResult(70, [new RequisitoMatch($java->id, 'Java', 70, NivelCoincidencia::Coincide, 'x', 'ok')], MotorMatching::Fake))->aArreglo();

    Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Terraform')->id, 'tipo' => TipoRequisito::Deseable]);

    $cumplimiento = CumplimientoDeRequisitos::de($detalle, $this->backend->requisitos()->with('competencia')->get());

    expect($cumplimiento->total())->toBe(2)
        ->and($cumplimiento->cumplidos())->toBe(1)
        ->and($cumplimiento->cubreLosObligatorios())->toBeTrue()
        ->and(CumplimientoDeRequisitos::de(null, $this->backend->requisitos()->with('competencia')->get())->hayCalculo())->toBeFalse();
});

it('la vista del ranking se abre desde la dirección y /matching lleva a ella', function () {
    $this->get('/seleccion?vista=ranking')->assertOk()->assertSee('Ordenado por coincidencia');
    $this->get('/matching')->assertRedirect('/seleccion?vista=ranking');
});

it('una vacante masiva muestra de a 25 tarjetas por columna y cuenta todas', function () {
    Postulacion::factory()->count(60)->paraVacante($this->backend)->enEtapa(EtapaPostulacion::EnRevision)->create();

    $pantalla = Livewire::test(Central::class, ['vacante' => $this->backend]);

    // La columna cuenta las 61 (60 + Sofía) y dibuja 25; el resto, tras «Ver más».
    expect(substr_count($pantalla->html(), 'wire:key="postulacion-'))->toBe(25 + 2 + 1 + 0) // en revisión (25) + postulado (2) + descartado (1)
        ->and($pantalla->html())->toContain('Ver más (25 de 36 restantes)');

    $pantalla->call('verMas', 'en_revision');

    expect(substr_count($pantalla->html(), 'wire:key="postulacion-'))->toBe(50 + 2 + 1)
        ->and($pantalla->html())->toContain('Ver más (11 de 11 restantes)');
});

it('un cliente no puede pedir más tarjetas de las permitidas manipulando la propiedad', function () {
    Postulacion::factory()->count(30)->paraVacante($this->backend)->enEtapa(EtapaPostulacion::EnRevision)->create();

    $pantalla = Livewire::test(Central::class, ['vacante' => $this->backend])
        ->set('mostrando', ['en_revision' => 100000000, 'postulado' => -5, 'entrevista' => 'muchas']);

    // En revisión hay 31 (30 + Sofía): se pintan las 31 (tope 500, no 100 millones); los valores absurdos vuelven a un bloque.
    expect(substr_count($pantalla->html(), 'wire:key="postulacion-'))->toBe(31 + 2 + 1);

    // Ni siquiera repitiendo «Ver más» se pasa del tope.
    foreach (range(1, 30) as $_) {
        $pantalla->call('verMas', 'en_revision');
    }

    expect($pantalla->get('mostrando')['en_revision'])->toBe(Central::MAXIMO_POR_COLUMNA);
});

it('el ranking se pagina en SQL y ordena por coincidencia sin leer a toda la vacante', function () {
    Postulacion::factory()->count(40)->paraVacante($this->backend)->create(['match_score' => 5]);

    $pantalla = Livewire::test(Central::class, ['vacante' => $this->backend])->set('vista', 'ranking');
    $html = $pantalla->html();

    // Primera página: 25 filas, empezando por la mejor (Sofía, 91 %).
    expect(substr_count($html, 'wire:key="ranking-'))->toBe(25)
        ->and(mb_strpos($html, 'Sofía Nieto'))->toBeLessThan(mb_strpos($html, 'Elena Ruiz'));

    $pantalla->call('gotoPage', 2);
    expect(substr_count($pantalla->html(), 'wire:key="ranking-'))->toBe(18); // 43 sin descartados (Pablo) − 25
});

it('mover una tarjeta no carga ni bloquea a toda la vacante y deja cada columna numerada sin huecos', function () {
    Postulacion::factory()->count(30)->paraVacante($this->backend)->enEtapa(EtapaPostulacion::Postulado)->create();
    app(MoverPostulacion::class)->ejecutar($this->elena->fresh(['vacante']), EtapaPostulacion::Oferta, 0, $this->marta);
    app(MoverPostulacion::class)->ejecutar($this->marco->fresh(['vacante']), EtapaPostulacion::Oferta, 0, $this->marta);

    $ordenes = Postulacion::where('vacante_id', $this->backend->id)->where('etapa', EtapaPostulacion::Oferta)->orderBy('orden')->pluck('orden', 'id')->values()->all();
    $postulado = Postulacion::where('vacante_id', $this->backend->id)->where('etapa', EtapaPostulacion::Postulado)->orderBy('orden')->pluck('orden')->all();

    expect($ordenes)->toBe([0, 1])
        ->and($postulado)->toBe(range(0, 29))
        ->and(Postulacion::find($this->marco->id)->orden)->toBe(0);
});

it('el resumen de cumplimiento se guarda al calcular el matching', function () {
    $java = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'tipo' => TipoRequisito::Obligatorio]);
    Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Terraform')->id, 'tipo' => TipoRequisito::Obligatorio]);

    $cv = Cv::factory()->procesado('5 años de experiencia con Java en proyectos de alto tráfico.')->create(['candidato_id' => $this->elena->candidato_id]);
    $this->elena->update(['cv_id' => $cv->id]);

    CalcularMatch::dispatchSync($this->nimbus->id, $this->elena->id);

    $elena = $this->elena->fresh();
    expect($elena->requisitos_total)->toBe(2)
        ->and($elena->requisitos_cubiertos)->toBe(1)
        ->and($elena->obligatorios_faltantes)->toBe(1)
        ->and($elena->tieneResumenDeCumplimiento())->toBeTrue()
        ->and($elena->resumenDeCumplimiento())->toBe('1 de 2 requisitos');
});

it('el ranking dibuja el mapa de calor de cobertura solo si hay dos o más análisis que comparar', function () {
    $java = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'tipo' => TipoRequisito::Obligatorio]);
    $docker = Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Docker')->id, 'tipo' => TipoRequisito::Deseable]);

    $guardar = function (Postulacion $postulacion, NivelCoincidencia $nivelJava, NivelCoincidencia $nivelDocker) use ($java, $docker) {
        $detalle = (new MatchResult(70, [
            new RequisitoMatch($java->id, 'Java', 80, $nivelJava, 'Java 5 años', 'ok'),
            new RequisitoMatch($docker->id, 'Docker', 50, $nivelDocker, null, 'ok'),
        ], MotorMatching::Fake))->aArreglo();
        $postulacion->update(['match_detalle' => $detalle, 'match_calculado_at' => now(), ...CumplimientoDeRequisitos::de($detalle, $this->backend->requisitos()->with('competencia')->get())->columnas()]);
    };

    // Un solo análisis: no hay con quién comparar, el mapa no aparece.
    $guardar($this->elena, NivelCoincidencia::Coincide, NivelCoincidencia::NoEncontrado);
    Livewire::test(Central::class, ['vacante' => $this->backend])->set('vista', 'ranking')
        ->assertDontSee('Cobertura de requisitos')
        ->assertDontSeeHtml('class="viz"');

    // Con dos, aparece como tabla con las dos filas y los dos requisitos.
    $guardar($this->sofia, NivelCoincidencia::Revisar, NivelCoincidencia::Coincide);
    $html = Livewire::test(Central::class, ['vacante' => $this->backend])->set('vista', 'ranking')
        ->assertSee('Cobertura de requisitos')
        ->html();

    expect(substr_count($html, 'wire:key="mapa-'))->toBe(2)
        ->and($html)->toContain('Java: Coincide')->toContain('Java: Revisar')->toContain('Docker: No encontrado')->toContain('Docker: Coincide');

    // Sin calcular a nadie más: Marco no aparece en el mapa (solo quienes tienen análisis).
    expect($html)->not->toContain('mapa-'.$this->marco->id);
});
