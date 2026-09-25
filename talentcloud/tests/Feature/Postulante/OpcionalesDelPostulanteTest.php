<?php

use App\DTO\FichaDelPostulante;
use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\Modalidad;
use App\Enums\TipoActividad;
use App\Jobs\AvisarEstadoDePostulacion;
use App\Livewire\Empleos\Detalle as EmpleoDetalle;
use App\Livewire\Empleos\Listado as Empleos;
use App\Livewire\Kanban\Tablero;
use App\Livewire\Postulante\MisPostulaciones;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoPostulacionRetirada;
use App\Services\Cuenta\ActualizarPerfil;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\RetirarPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/*
 * Paquete 5 (ampliación 2): opcionales del postulante. Retirar su postulación,
 * editar titular y teléfono, descargar sus datos y la bolsa pública de empleos.
 */

beforeEach(function () {
    Notification::fake();
    Queue::fake([AvisarEstadoDePostulacion::class]);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->cuentaNimbus = User::factory()->deEmpresa($this->nimbus)->empresa()->create();
        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
        $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Diego Ferro']);

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)
            ->create(['titulo' => 'Desarrollador Backend', 'descripcion' => 'APIs REST.', 'creada_por' => $this->marta->id, 'publicada_at' => now()->subDay()]);
        Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id]);
        $this->borrador = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Borrador)->create(['titulo' => 'QA Engineer']);
        $this->cerrada = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'DevOps Engineer']);
    });

    $this->marco = User::factory()->postulante()->create(['name' => 'Marco Díaz']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marco->id, 'email' => $this->marco->email]);
    $this->cv = Cv::factory()->procesado()->create(['candidato_id' => $this->candidato->id, 'nombre_original' => 'CV_MarcoDiaz.pdf']);

    $this->postulacion = app(TenantContext::class)->ejecutarGlobal(
        fn () => Postulacion::factory()->paraVacante($this->backend)->enEtapa(EtapaPostulacion::EnRevision)->create(['candidato_id' => $this->candidato->id]),
    );

    actuarComo($this->marco);
});

function entrevistaPendienteDe(Postulacion $postulacion, User $entrevistador): Entrevista
{
    return app(TenantContext::class)->ejecutarGlobal(fn () => Entrevista::factory()->create([
        'empresa_id' => $postulacion->empresa_id,
        'postulacion_id' => $postulacion->id,
        'entrevistador_id' => $entrevistador->id,
        'inicia_at' => now()->addDays(3),
        'estado' => EstadoEntrevista::Pendiente,
    ]));
}

// — Retirar postulación —

it('retira la postulación: Descartado en el Kanban, "Retirada" para el postulante', function () {
    // Con varias postulaciones en el listado (la carga diferida está prohibida fuera de producción).
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()
        ->paraVacante(Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create())
        ->create(['candidato_id' => $this->candidato->id]));

    Livewire::test(MisPostulaciones::class)
        ->call('verDetalle', $this->postulacion->id)
        ->assertSee('Retirar postulación')
        ->call('confirmarRetiro')
        ->assertSet('mostrarRetiro', true)
        ->call('retirar')
        ->assertHasNoErrors()
        ->assertSee('Estado: Retirada')
        ->assertDontSeeHtml('wire:click="confirmarRetiro"');

    $postulacion = app(TenantContext::class)->ejecutarGlobal(fn () => $this->postulacion->fresh(['vacante']));

    expect($postulacion->etapa)->toBe(EtapaPostulacion::Descartado)
        ->and($postulacion->retirada_at)->not->toBeNull()
        ->and(EstadoDeMiPostulacion::de($postulacion))->toBe(EstadoDeMiPostulacion::Retirada)
        ->and(EstadoDeMiPostulacion::aAvisar($postulacion))->toBeNull();
});

it('lo anota como retirada y avisa a la empresa, no al propio postulante', function () {
    app(RetirarPostulacion::class)->ejecutar($this->postulacion, $this->marco);

    $actividad = app(TenantContext::class)->ejecutarPara($this->nimbus->id, fn () => Actividad::query()->pluck('descripcion', 'tipo')->all());

    expect($actividad)->toBe([TipoActividad::PostulacionRetirada->value => 'Marco Díaz retiró su postulación a Desarrollador Backend.']);

    Notification::assertSentTo([$this->marta, $this->cuentaNimbus], AvisoPostulacionRetirada::class);
    Notification::assertNotSentTo($this->diego, AvisoPostulacionRetirada::class);
    Notification::assertNothingSentTo($this->marco);
    Queue::assertNotPushed(AvisarEstadoDePostulacion::class);
});

it('cancela sus entrevistas pendientes y avisa solo al entrevistador', function () {
    $entrevista = entrevistaPendienteDe($this->postulacion, $this->diego);

    app(RetirarPostulacion::class)->ejecutar($this->postulacion, $this->marco);

    expect(app(TenantContext::class)->ejecutarGlobal(fn () => $entrevista->fresh()->estado))->toBe(EstadoEntrevista::Cancelada);
    Notification::assertSentTo($this->diego, AvisoDeEntrevista::class);
    Notification::assertNotSentTo($this->marco, AvisoDeEntrevista::class);
});

it('no deja retirar un proceso terminado ni una vacante cerrada', function (string $caso) {
    app(TenantContext::class)->ejecutarGlobal(function () use ($caso) {
        match ($caso) {
            'contratado' => $this->postulacion->update(['etapa' => EtapaPostulacion::Contratado]),
            'descartado' => $this->postulacion->update(['etapa' => EtapaPostulacion::Descartado]),
            'vacante cerrada' => $this->backend->update(['estado' => EstadoVacante::Cerrada]),
            'ya retirada' => $this->postulacion->forceFill(['retirada_at' => now()])->save(),
        };
    });

    expect(fn () => app(RetirarPostulacion::class)->ejecutar($this->postulacion, $this->marco))->toThrow(OperacionNoPermitida::class);
})->with(['contratado', 'descartado', 'vacante cerrada', 'ya retirada']);

it('solo el propio postulante puede retirarla', function () {
    $otro = User::factory()->postulante()->create();
    Candidato::factory()->create(['user_id' => $otro->id]);

    expect($otro->can('retirar', $this->postulacion))->toBeFalse()
        ->and($this->marta->can('retirar', $this->postulacion))->toBeFalse()
        ->and($this->marco->can('retirar', $this->postulacion))->toBeTrue();

    actuarComo($otro);
    Livewire::test(MisPostulaciones::class)->call('verDetalle', $this->postulacion->id)->assertNotFound();
});

it('la empresa no puede devolver una retirada al proceso y la ve marcada en el Kanban', function () {
    app(RetirarPostulacion::class)->ejecutar($this->postulacion, $this->marco);
    actuarComo($this->marta);

    expect(fn () => app(MoverPostulacion::class)->ejecutar($this->postulacion->fresh(), EtapaPostulacion::EnRevision, 0, $this->marta))
        ->toThrow(OperacionNoPermitida::class, 'Marco Díaz retiró su postulación: no se puede volver a mover.');

    Livewire::test(Tablero::class, ['vacante' => $this->backend])
        ->assertSeeHtml('>Retirada</span>')
        ->call('abrir', $this->postulacion->id)
        ->assertSee('Retiró su postulación el')
        ->assertDontSee('Mover a');
});

it('cuenta las retiradas aparte de los descartes en el embudo', function () {
    app(RetirarPostulacion::class)->ejecutar($this->postulacion, $this->marco);
    actuarComo($this->marta);

    $embudo = app(EmbudoDeSeleccion::class)->calcular();

    expect($embudo->retiradas)->toBe(1)->and($embudo->descartadas)->toBe(0);
});

// — Titular y teléfono —

it('edita titular y teléfono desde Mi perfil', function () {
    Volt::test('profile.update-profile-information-form')
        ->assertSet('esPostulante', true)
        ->set('titular', 'Backend Java con 5 años')
        ->set('telefono', '+51 999 888 777')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($this->candidato->fresh())
        ->titular->toBe('Backend Java con 5 años')
        ->telefono->toBe('+51 999 888 777');

    Volt::test('profile.update-profile-information-form')
        ->set('telefono', 'llámame')
        ->call('updateProfileInformation')
        ->assertHasErrors('telefono');
});

it('no toca la ficha si no se envía, y el personal no ve esos campos', function () {
    $this->candidato->update(['titular' => 'Diseñador', 'telefono' => '123']);

    app(ActualizarPerfil::class)->ejecutar($this->marco, 'Marco D.', $this->marco->email, true);
    expect($this->candidato->fresh())->titular->toBe('Diseñador')->nombre->toBe('Marco D.');

    app(ActualizarPerfil::class)->ejecutar($this->marco, 'Marco D.', $this->marco->email, true, new FichaDelPostulante(null, null));
    expect($this->candidato->fresh())->titular->toBeNull()->telefono->toBeNull();

    actuarComo($this->marta);
    Volt::test('profile.update-profile-information-form')->assertSet('esPostulante', false)->assertDontSee('Titular');
});

// — Descargar mis datos —

it('descarga sus datos sin notas, evaluaciones, puntajes ni competencias manuales', function () {
    $java = Competencia::obtenerOCrear('Java');
    $kotlin = Competencia::obtenerOCrear('Kotlin');
    $this->candidato->todasLasCompetencias()->attach($java->id, ['fuente' => FuenteCompetencia::Extraccion->value, 'anios' => 5, 'evidencia' => 'Java 5 años']);
    $this->candidato->todasLasCompetencias()->attach($kotlin->id, ['fuente' => FuenteCompetencia::Manual->value, 'empresa_id' => $this->nimbus->id]);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->postulacion->update(['match_score' => 87.5]);
        $nota = new Nota(['texto' => 'Pedir referencias']);
        $nota->empresa_id = $this->nimbus->id;
        $nota->postulacion_id = $this->postulacion->id;
        $nota->autor_id = $this->marta->id;
        $nota->save();
    });

    $respuesta = $this->get(route('postulante.mis-datos'))->assertOk();

    expect($respuesta->headers->get('Content-Disposition'))->toContain('attachment; filename="mis-datos-talentcloud-');

    $datos = $respuesta->json();
    $json = $respuesta->getContent();

    expect($datos['cuenta']['email'])->toBe($this->marco->email)
        ->and($datos['cvs'][0]['archivo'])->toBe('CV_MarcoDiaz.pdf')
        ->and(array_column($datos['competencias_extraidas_de_tu_cv'], 'competencia'))->toBe(['Java'])
        ->and($datos['postulaciones'][0])->toMatchArray(['vacante' => 'Desarrollador Backend', 'empresa' => 'Nimbus Corp', 'estado' => 'En revisión'])
        ->and($json)->not->toContain('Pedir referencias')
        ->and($json)->not->toContain('Kotlin')
        ->and($json)->not->toContain('87.5')
        ->and($json)->not->toContain('match');
});

it('solo el postulante descarga sus datos', function () {
    $this->actingAs($this->marta)->get(route('postulante.mis-datos'))->assertForbidden();
    auth()->logout();
    $this->get(route('postulante.mis-datos'))->assertRedirect(route('login'));
});

// — Bolsa pública —

it('muestra sin sesión solo las vacantes abiertas, sin datos internos', function () {
    $this->app['auth']->forgetGuards();
    auth()->logout();

    $this->get(route('empleos.index'))
        ->assertOk()
        ->assertSee('Desarrollador Backend')
        ->assertSee('Java')
        ->assertDontSee('QA Engineer')
        ->assertDontSee('DevOps Engineer')
        ->assertDontSee('Marco Díaz');

    $this->get(route('empleos.show', $this->backend->id))
        ->assertOk()
        ->assertSee('APIs REST.')
        ->assertSee('Iniciar sesión para postular')
        ->assertDontSee('Marta Solís');

    $this->get(route('empleos.show', $this->borrador->id))->assertNotFound();
    $this->get(route('empleos.show', $this->cerrada->id))->assertNotFound();
});

it('filtra la bolsa pública por modalidad y texto', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)
        ->create(['titulo' => 'Product Designer', 'modalidad' => Modalidad::Presencial]));

    Livewire::test(Empleos::class)
        ->set('busqueda', 'designer')
        ->assertSee('Product Designer')
        ->assertDontSee('Desarrollador Backend');

    Livewire::test(EmpleoDetalle::class, ['id' => $this->backend->id])->assertSee('Desarrollador Backend');
});

it('muestra las vacantes abiertas en la presentación', function () {
    auth()->logout();

    $this->get('/')->assertOk()->assertSee('Vacantes abiertas ahora')->assertSee('Desarrollador Backend')->assertDontSee('QA Engineer');
});

it('lleva a iniciar sesión o registrarse y vuelve a la vacante', function () {
    auth()->logout();

    $this->get(route('empleos.postular', $this->backend->id))->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('empleos.postular', $this->backend->id));

    $this->get(route('empleos.postular', ['id' => $this->backend->id, 'cuenta' => 'nueva']))->assertRedirect(route('register.postulante'));

    Volt::test('pages.auth.register-postulante')
        ->set('name', 'Ana Nueva')
        ->set('email', 'ana@correo.com')
        ->set('password', 'password-segura-1')
        ->set('password_confirmation', 'password-segura-1')
        ->set('consentimiento', true)
        ->call('register')
        ->assertRedirect(route('empleos.postular', $this->backend->id));

    $this->actingAs($this->marco)->get(route('empleos.postular', $this->backend->id))
        ->assertRedirect(route('postulante.vacantes.show', $this->backend->id));

    $this->actingAs($this->marta)->get(route('empleos.postular', $this->backend->id))
        ->assertRedirect(route('empleos.show', $this->backend->id))
        ->assertSessionHas('aviso');

    $this->get(route('empleos.postular', $this->borrador->id))->assertNotFound();
});
