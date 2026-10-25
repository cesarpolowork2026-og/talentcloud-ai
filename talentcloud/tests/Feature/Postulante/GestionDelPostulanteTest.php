<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Jornada;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Livewire\Postulante\MisPostulaciones;
use App\Livewire\Postulante\Vacantes;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * El postulante ordena su lista («Quitar de mi lista», que solo oculta) y encuentra vacantes según lo que dice su CV
 * (análisis básico: los requisitos que el CV nombra, con sus sinónimos).
 */

beforeEach(function () {
    Queue::fake();

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();

        $requisitos = fn (Vacante $v, array $lista) => collect($lista)->each(fn ($r, $i) => Requisito::factory()->create([
            'vacante_id' => $v->id, 'competencia_id' => Competencia::obtenerOCrear($r[0])->id, 'tipo' => $r[1], 'orden' => $i,
        ]));

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create([
            'titulo' => 'Desarrollador Backend', 'modalidad' => Modalidad::Remoto, 'ubicacion' => null, 'jornada' => Jornada::Completa,
            'creada_por' => $this->marta->id, 'publicada_at' => now()->subDays(5),
        ]);
        $requisitos($this->backend, [['Java', TipoRequisito::Obligatorio], ['Docker', TipoRequisito::Deseable]]);

        $this->cocina = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create([
            'titulo' => 'Cocinero de Línea', 'modalidad' => Modalidad::Presencial, 'ubicacion' => 'Trujillo', 'jornada' => Jornada::Parcial,
            'creada_por' => $this->marta->id, 'publicada_at' => now()->subDay(),
        ]);
        $requisitos($this->cocina, [['Manipulación de alimentos', TipoRequisito::Obligatorio]]);
    });

    $this->renzo = User::factory()->postulante()->create(['name' => 'Renzo Paredes']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Renzo Paredes', 'user_id' => $this->renzo->id]);
    $this->cv = Cv::factory()->procesado('Cinco años con Java y Spring Boot. Uso de Docker en producción.')
        ->create(['candidato_id' => $this->candidato->id]);

    actuarComo($this->renzo);
});

function postulacionDeRenzo(object $prueba, Vacante $vacante, EtapaPostulacion $etapa): Postulacion
{
    return app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($vacante)
        ->enEtapa($etapa)->create(['candidato_id' => $prueba->candidato->id]));
}

// — Quitar de mi lista —

it('quita de su lista una postulación finalizada sin borrarla: la empresa la sigue viendo', function () {
    $descartada = postulacionDeRenzo($this, $this->backend, EtapaPostulacion::Descartado);

    Livewire::test(MisPostulaciones::class)
        ->assertSee('Desarrollador Backend')
        ->assertSee('Quitar las finalizadas (1)')
        ->call('verDetalle', $descartada->id)
        ->call('confirmarQuitar')
        ->assertSee('No se borra nada')
        ->call('quitar')
        ->assertDispatched('postulaciones-quitadas')
        ->assertDontSeeHtml('wire:key="mi-postulacion-'.$descartada->id.'"')
        ->assertSee('Ver las que quitaste (1)');

    $fila = Postulacion::sinFiltroDeEmpresa()->find($descartada->id);
    expect($fila)->not->toBeNull()
        ->and($fila->oculta_para_postulante_at)->not->toBeNull()
        ->and($fila->etapa)->toBe(EtapaPostulacion::Descartado)
        ->and($fila->retirada_at)->toBeNull();
});

it('quitar una postulación en curso primero la retira (la empresa la ve como retirada)', function () {
    $enCurso = postulacionDeRenzo($this, $this->backend, EtapaPostulacion::EnRevision);

    Livewire::test(MisPostulaciones::class)
        ->call('verDetalle', $enCurso->id)
        ->call('confirmarQuitar')
        ->assertSee('primero se retira')
        ->assertSee('Retirar y quitar')
        ->call('quitar');

    $fila = Postulacion::sinFiltroDeEmpresa()->find($enCurso->id);
    expect($fila->retirada_at)->not->toBeNull()
        ->and($fila->etapa)->toBe(EtapaPostulacion::Descartado)
        ->and($fila->oculta_para_postulante_at)->not->toBeNull();
});

it('«Quitar las finalizadas» no toca las que siguen en curso', function () {
    $descartada = postulacionDeRenzo($this, $this->backend, EtapaPostulacion::Descartado);
    $enCurso = postulacionDeRenzo($this, $this->cocina, EtapaPostulacion::Entrevista);

    Livewire::test(MisPostulaciones::class)->call('quitarFinalizadas')->assertSee('Cocinero de Línea');

    expect(Postulacion::sinFiltroDeEmpresa()->find($descartada->id)->oculta_para_postulante_at)->not->toBeNull()
        ->and(Postulacion::sinFiltroDeEmpresa()->find($enCurso->id)->oculta_para_postulante_at)->toBeNull()
        ->and(Postulacion::sinFiltroDeEmpresa()->find($enCurso->id)->retirada_at)->toBeNull();
});

it('puede ver las que quitó y volver a mostrarlas', function () {
    $descartada = postulacionDeRenzo($this, $this->backend, EtapaPostulacion::Descartado);
    $pantalla = Livewire::test(MisPostulaciones::class)->call('quitarFinalizadas');

    $pantalla->call('alternarQuitadas')
        ->assertSee('Estas son las postulaciones que quitaste')
        ->assertSee('Desarrollador Backend')
        ->call('verDetalle', $descartada->id)
        ->assertSee('Volver a mostrar en mi lista')
        ->call('volverAMostrar')
        ->call('alternarQuitadas')
        ->assertSee('Desarrollador Backend');

    expect(Postulacion::sinFiltroDeEmpresa()->find($descartada->id)->oculta_para_postulante_at)->toBeNull();
});

it('no puede quitar la postulación de otra persona', function () {
    $ajena = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)
        ->enEtapa(EtapaPostulacion::Descartado)->create(['candidato_id' => Candidato::factory()->create()->id]));

    Livewire::test(MisPostulaciones::class)->call('verDetalle', $ajena->id)->assertNotFound();
    expect(Postulacion::sinFiltroDeEmpresa()->find($ajena->id)->oculta_para_postulante_at)->toBeNull();
});

// — Vacantes según el CV —

it('muestra cuánto de cada vacante menciona su CV y recomienda una sola: la que más requisitos menciona', function () {
    Livewire::test(Vacantes::class)
        ->assertSee('Recomendada para ti')
        ->assertSeeHtml('wire:key="recomendada-'.$this->backend->id.'"')
        ->assertDontSeeHtml('wire:key="recomendada-'.$this->cocina->id.'"')
        ->assertSee('Tu CV menciona los obligatorios')
        ->assertSee('Tu CV menciona 2 de 2 requisitos')
        ->assertSee('Tu CV menciona 0 de 1 requisito')
        ->assertSee('no menciona: Manipulación de alimentos');
});

it('ordena por compatibilidad y filtra las que su CV cumple en lo obligatorio', function () {
    // Por fecha, la de cocina (más reciente) va primero; por compatibilidad, la de backend.
    $html = Livewire::test(Vacantes::class)->set('ordenar', 'compatibles')->html();
    expect(strpos($html, 'Desarrollador Backend'))->toBeLessThan(strpos($html, 'Cocinero de Línea'));

    Livewire::test(Vacantes::class)->set('soloCompatibles', true)
        ->assertSee('Desarrollador Backend')
        ->assertDontSee('Cocinero de Línea')
        ->assertSee('1 vacante abierta con esos filtros');
});

it('no recomienda una vacante a la que ya postuló', function () {
    postulacionDeRenzo($this, $this->backend, EtapaPostulacion::EnRevision);

    // La de cocina no menciona ningún requisito del CV: sin candidata, no hay recomendación.
    Livewire::test(Vacantes::class)->assertDontSee('Recomendada para ti');
});

it('filtra por lugar y por jornada', function () {
    Livewire::test(Vacantes::class)->set('lugar', 'Trujillo')->assertSee('Cocinero de Línea')->assertDontSee('Desarrollador Backend');
    Livewire::test(Vacantes::class)->set('jornada', 'completa')->assertSee('Desarrollador Backend')->assertDontSee('Cocinero de Línea');
});

it('sin un CV listo invita a subirlo y no ofrece ordenar por compatibilidad', function () {
    $this->cv->delete();

    Livewire::test(Vacantes::class)
        ->assertSee('Sube tu CV')
        ->assertDontSee('Recomendada para ti')
        ->assertDontSee('Más compatibles con mi CV')
        ->assertDontSee('Tu CV menciona');
});
