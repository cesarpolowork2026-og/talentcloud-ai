<?php

use App\DTO\NuevaEntrevistaData;
use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\ModalidadEntrevista;
use App\Enums\OrigenPostulacion;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\PostularCandidato;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/*
 * Toda acción del equipo sobre la postulación de una persona le llega a su campana: que la registren en una vacante,
 * cada cambio de etapa (también la Oferta) y cada cambio de sus entrevistas. Lo que hace ella misma no se le avisa.
 */

beforeEach(function () {
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
        $this->vacante = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create([
            'titulo' => 'Analista de Datos', 'creada_por' => $this->marta->id, 'publicada_at' => now()->subDay(),
        ]);
    });

    $this->renzo = User::factory()->postulante()->create(['name' => 'Renzo Paredes']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Renzo Paredes', 'user_id' => $this->renzo->id]);
    Cv::factory()->procesado('Analista con SQL y Python.')->create(['candidato_id' => $this->candidato->id]);
});

/** Textos de la campana de Renzo, del más antiguo al más reciente (cada acción pasa un segundo después: ver despues()). */
function campanaDeRenzo(object $prueba): array
{
    return $prueba->renzo->notifications()->reorder('created_at')->get()->map(fn ($n) => $n->data['texto'])->all();
}

/** Los avisos guardan la fecha al segundo: cada acción se hace un segundo después de la anterior para ordenarlos. */
function despues(): void
{
    test()->travel(1)->seconds();
}

function postulacionRegistradaPorElEquipo(object $prueba): Postulacion
{
    actuarComo($prueba->marta);

    return app(PostularCandidato::class)->ejecutar($prueba->vacante, $prueba->candidato);
}

function moverPostulacionDeRenzo(object $prueba, Postulacion $postulacion, EtapaPostulacion $etapa): void
{
    despues();
    actuarComo($prueba->marta);
    app(MoverPostulacion::class)->ejecutar($postulacion->fresh(), $etapa, 0, $prueba->marta);
}

function entrevistaDeRenzo(object $prueba, Postulacion $postulacion, bool $mover = false): Entrevista
{
    despues();
    actuarComo($prueba->marta);

    return app(ProgramarEntrevista::class)->ejecutar(new NuevaEntrevistaData(
        postulacionId: $postulacion->id,
        entrevistadorId: $prueba->marta->id,
        iniciaAt: CarbonImmutable::now()->addDays(2)->setTime(10, 0),
        duracionMin: 45,
        modalidad: ModalidadEntrevista::Videollamada,
        enlace: 'https://meet.example.com/abc',
        notas: null,
        moverAEntrevista: $mover,
    ), $prueba->marta);
}

it('avisa al postulante cuando la empresa lo registra en una vacante', function () {
    $postulacion = postulacionRegistradaPorElEquipo($this);

    expect($postulacion->origen)->toBe(OrigenPostulacion::Equipo)
        ->and(campanaDeRenzo($this))->toBe(['Nimbus Corp te registró como postulante a Analista de Datos. Puedes seguir tu proceso en «Mis postulaciones».'])
        ->and($postulacion->fresh()->estado_avisado)->toBe(EstadoDeMiPostulacion::Postulado);
});

it('no le avisa de su propia postulación', function () {
    actuarComo($this->renzo);
    app(PostularCandidato::class)->ejecutar($this->vacante, $this->candidato, OrigenPostulacion::Postulante);

    expect(campanaDeRenzo($this))->toBe([]);
});

it('avisa cada etapa, también la entrevista sin fecha y la oferta, que ya no se oculta', function () {
    $postulacion = postulacionRegistradaPorElEquipo($this);

    moverPostulacionDeRenzo($this, $postulacion, EtapaPostulacion::EnRevision);
    moverPostulacionDeRenzo($this, $postulacion, EtapaPostulacion::Entrevista);

    // Oferta exige una entrevista realizada (se crea directamente: no es lo que se prueba aquí).
    app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'postulacion_id' => $postulacion->id, 'empresa_id' => $this->nimbus->id, 'entrevistador_id' => $this->marta->id,
        'inicia_at' => now()->subDay(), 'estado' => EstadoEntrevista::Completada,
    ]));
    moverPostulacionDeRenzo($this, $postulacion, EtapaPostulacion::Oferta);

    expect(array_slice(campanaDeRenzo($this), 1))->toBe([
        'Nimbus Corp está revisando tu postulación a Analista de Datos.',
        'Avanzaste a la etapa de entrevistas de Analista de Datos. Nimbus Corp se pondrá en contacto contigo para coordinarla.',
        '¡Buenas noticias! Nimbus Corp te hizo una oferta para Analista de Datos. Pronto se pondrá en contacto contigo para conversar los detalles.',
    ])->and(EstadoDeMiPostulacion::de($postulacion->fresh()))->toBe(EstadoDeMiPostulacion::Oferta);
});

it('al programar la entrevista con «Mover a la etapa Entrevista» avisa una sola vez, con la fecha', function () {
    $postulacion = postulacionRegistradaPorElEquipo($this);

    entrevistaDeRenzo($this, $postulacion, mover: true);

    $avisos = array_slice(campanaDeRenzo($this), 1);
    expect($avisos)->toHaveCount(1)
        ->and($avisos[0])->toStartWith('Tienes una entrevista para Analista de Datos en Nimbus Corp el ')
        ->and($postulacion->fresh()->estado_avisado)->toBe(EstadoDeMiPostulacion::Entrevista);
});

it('avisa cuando la entrevista se confirma y cuando se marca como realizada', function () {
    $postulacion = postulacionRegistradaPorElEquipo($this);
    $entrevista = entrevistaDeRenzo($this, $postulacion, mover: true);

    despues();
    app(CambiarEstadoEntrevista::class)->confirmar($entrevista);
    $this->travel(3)->days();
    app(CambiarEstadoEntrevista::class)->completar($entrevista->fresh());

    $avisos = array_slice(campanaDeRenzo($this), 2);
    expect($avisos)->toHaveCount(2)
        ->and($avisos[0])->toStartWith('Nimbus Corp confirmó tu entrevista para Analista de Datos el ')
        ->and($avisos[1])->toBe('Nimbus Corp registró como realizada tu entrevista para Analista de Datos. Te avisaremos cuando haya novedades.');
});

it('avisa si la postulación vuelve al inicio del proceso', function () {
    $postulacion = postulacionRegistradaPorElEquipo($this);

    moverPostulacionDeRenzo($this, $postulacion, EtapaPostulacion::EnRevision);
    moverPostulacionDeRenzo($this, $postulacion, EtapaPostulacion::Postulado);

    expect(last(campanaDeRenzo($this)))->toBe('Tu postulación a Analista de Datos en Nimbus Corp volvió a la etapa «Postulado»: sigues en el proceso.');
});
