<?php

use App\DTO\DatosDeEntrevista;
use App\DTO\EvaluacionData;
use App\DTO\NuevaEntrevistaData;
use App\DTO\NuevaVacanteData;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Modalidad;
use App\Enums\ModalidadEntrevista;
use App\Enums\OrigenPostulacion;
use App\Enums\TipoActividad;
use App\Events\CvAnalizado;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoDeEntrevista;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\CrearVacante;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Diego Ferro', 'notificaciones_email' => false]);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);

    // Sofía no tiene cuenta (la registró la empresa); Marco es postulante con cuenta.
    $this->sofia = Postulacion::factory()->paraVacante($this->backend)->create([
        'candidato_id' => Candidato::factory()->create(['nombre' => 'Sofía Nieto', 'email' => 'sofia@correo.com'])->id,
    ]);
    $this->marcoUsuario = User::factory()->postulante()->create(['name' => 'Marco Díaz']);
    $this->marco = Postulacion::factory()->paraVacante($this->backend)->create([
        'candidato_id' => Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marcoUsuario->id])->id,
    ]);

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

function descripciones(): array
{
    return Actividad::query()->latest('id')->pluck('descripcion')->all();
}

function programarCon(Postulacion $postulacion, User $entrevistador): Entrevista
{
    return app(ProgramarEntrevista::class)->ejecutar(new NuevaEntrevistaData(
        postulacionId: $postulacion->id,
        entrevistadorId: $entrevistador->id,
        iniciaAt: CarbonImmutable::parse('2026-09-25 10:00'),
        duracionMin: 45,
        modalidad: ModalidadEntrevista::Videollamada,
        enlace: 'https://meet.example.com/abc',
        notas: null,
    ));
}

// — Actividad reciente —

it('anota quién registró una postulación del equipo, con la postulación como sujeto', function () {
    $lucia = Candidato::factory()->create(['nombre' => 'Lucía Prat']);

    $postulacion = app(PostularCandidato::class)->ejecutar($this->backend, $lucia);

    $actividad = Actividad::query()->latest('id')->firstOrFail();
    expect($actividad->descripcion)->toBe('Marta Solís registró a Lucía Prat en Desarrollador Backend.')
        ->and($actividad->tipo)->toBe(TipoActividad::PostulacionRegistrada->value)
        ->and($actividad->actor_id)->toBe($this->marta->id)
        ->and($actividad->sujeto_id)->toBe($postulacion->id);
});

it('no anota como registro del equipo la postulación que hace el propio postulante', function () {
    app(PostularCandidato::class)->ejecutar($this->backend, Candidato::factory()->create(), OrigenPostulacion::Postulante);

    expect(Actividad::where('tipo', TipoActividad::PostulacionRegistrada->value)->exists())->toBeFalse();
});

it('anota los cambios de etapa en la actividad', function () {
    $mover = app(MoverPostulacion::class);

    $mover->ejecutar($this->sofia, EtapaPostulacion::Entrevista, 0, $this->marta);
    $mover->ejecutar($this->sofia, EtapaPostulacion::EnRevision, 0, $this->marta);
    $mover->descartar($this->sofia, $this->marta);

    expect(descripciones())->toBe([
        'Se descartó a Sofía Nieto de Desarrollador Backend.',
        'Sofía Nieto volvió a la etapa de en revisión.',
        'Sofía Nieto avanzó a la etapa de entrevista.',
    ]);
    expect(Actividad::first()->actor_id)->toBe($this->marta->id);
});

it('anota entrevistas programadas y canceladas', function () {
    Notification::fake();

    $entrevista = programarCon($this->sofia, $this->diego);
    app(CambiarEstadoEntrevista::class)->cancelar($entrevista);

    expect(descripciones())->toBe([
        'Se canceló la entrevista de Sofía Nieto del 25 sep · 10:00.',
        'Marta Solís programó una entrevista con Sofía Nieto para el 25 sep · 10:00.',
    ]);
});

it('anota la evaluación aprobada', function () {
    Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'inicia_at' => now()->subDay(), 'estado' => EstadoEntrevista::Completada]);

    app(GuardarEvaluacion::class)->registrar($this->sofia->id, $this->marta, new EvaluacionData(
        ['conocimiento_tecnico' => 9, 'comunicacion' => 8, 'resolucion_de_problemas' => 9, 'ajuste_cultural' => 8],
        null,
        EstadoEvaluacion::Aprobado,
    ));

    expect(descripciones())->toBe(['Marta Solís aprobó la evaluación de Sofía Nieto.']);
});

it('anota el CV analizado en cada empresa donde el candidato postuló', function () {
    Notification::fake();
    $vela = app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        Postulacion::factory()->paraVacante(Vacante::factory()->deEmpresa($vela)->create(['titulo' => 'DevOps Engineer']))
            ->create(['candidato_id' => $this->sofia->candidato_id]);

        return $vela;
    });

    // Como en la cola "ia": sin contexto de empresa.
    app(TenantContext::class)->ejecutarGlobal(fn () => CvAnalizado::dispatch(Cv::factory()->procesado()->create(['candidato_id' => $this->sofia->candidato_id])));

    expect(descripciones())->toBe(['La IA analizó el CV de Sofía Nieto para Desarrollador Backend.']);
    $deVela = app(TenantContext::class)->ejecutarPara($vela->id, fn () => Actividad::pluck('descripcion')->all());
    expect($deVela)->toBe(['La IA analizó el CV de Sofía Nieto para DevOps Engineer.']);
});

it('anota la vacante creada', function () {
    app(CrearVacante::class)->ejecutar($this->nimbus, new NuevaVacanteData('QA Engineer', null, Modalidad::Remoto, ['Selenium']), $this->marta);

    expect(descripciones())->toContain('Nimbus Corp creó la vacante QA Engineer.');
});

// — Notificaciones —

it('avisa de la entrevista al entrevistador y al candidato sin cuenta por correo', function () {
    Notification::fake();

    programarCon($this->sofia, $this->diego);

    Notification::assertSentTo($this->diego, AvisoDeEntrevista::class, function (AvisoDeEntrevista $aviso, array $canales) {
        return $canales === ['database'] // Diego desactivó el correo
            && $aviso->texto() === 'Entrevista con Sofía Nieto para Desarrollador Backend el 25 sep · 10:00.';
    });
    Notification::assertSentOnDemand(AvisoDeEntrevista::class, function (AvisoDeEntrevista $aviso, array $canales, AnonymousNotifiable $destino) {
        return $destino->routes['mail'] === 'sofia@correo.com'
            && $canales === ['mail']
            && $aviso->texto() === 'Tienes una entrevista para Desarrollador Backend en Nimbus Corp el 25 sep · 10:00.';
    });
    Notification::assertNotSentTo($this->marta, AvisoDeEntrevista::class); // quien programa no se avisa a sí misma
});

it('avisa al postulante con cuenta en la campana y por correo', function () {
    Notification::fake();

    $entrevista = programarCon($this->marco, $this->marta);
    app(CambiarEstadoEntrevista::class)->cancelar($entrevista);

    Notification::assertSentTo($this->marcoUsuario, AvisoDeEntrevista::class, fn ($aviso, $canales) => $canales === ['database', 'mail'] && ! $aviso->cancelada);
    Notification::assertSentTo($this->marcoUsuario, AvisoDeEntrevista::class, fn ($aviso) => $aviso->texto() === 'Nimbus Corp canceló tu entrevista para Desarrollador Backend del 25 sep · 10:00.');
    Notification::assertNotSentTo($this->marta, AvisoDeEntrevista::class);
});

it('guarda el aviso en la campana con enlace a su pantalla', function () {
    programarCon($this->sofia, $this->diego);

    $notificacion = $this->diego->notifications()->sole();
    expect($notificacion->data)->toBe([
        'texto' => 'Entrevista con Sofía Nieto para Desarrollador Backend el 25 sep · 10:00.',
        'url' => route('entrevistas.index'),
        'candidato_id' => $this->sofia->candidato_id, // para borrarlo si el candidato elimina su cuenta
    ]);
});

it('envía los avisos por la cola y con el enlace de la videollamada', function () {
    $aviso = AvisoDeEntrevista::programadaParaCandidato(DatosDeEntrevista::de(programarCon($this->sofia, $this->diego)));
    $correo = $aviso->toMail(new AnonymousNotifiable);

    expect($aviso)->toBeInstanceOf(ShouldQueue::class)
        ->and($correo->subject)->toBe('Entrevista programada: Desarrollador Backend')
        ->and($correo->actionText)->toBe('Unirse a la videollamada')
        ->and($correo->actionUrl)->toBe('https://meet.example.com/abc');
});

it('avisa del CV analizado a quien lo subió', function () {
    Notification::fake();
    $datos = ['competencias' => [
        ['nombre' => 'Java', 'dudosa' => false], ['nombre' => 'Docker', 'dudosa' => false], ['nombre' => 'Kubernetes', 'dudosa' => true],
    ]];

    $delReclutador = Cv::factory()->procesado()->create(['candidato_id' => $this->sofia->candidato_id, 'subido_por' => $this->marta->id, 'datos_estructurados' => $datos]);
    $propio = Cv::factory()->procesado()->create(['candidato_id' => $this->marco->candidato_id, 'subido_por' => $this->marcoUsuario->id, 'datos_estructurados' => $datos]);
    CvAnalizado::dispatch($delReclutador);
    CvAnalizado::dispatch($propio);

    Notification::assertSentTo($this->marta, AvisoCvAnalizado::class, fn ($aviso) => $aviso->texto === 'Nuevo CV analizado de Sofía Nieto: 2 competencias detectadas.');
    Notification::assertSentTo($this->marcoUsuario, AvisoCvAnalizado::class, fn ($aviso) => $aviso->texto === 'Tu CV fue analizado: 2 competencias detectadas.');
});

it('no repite avisos ni actividad al reanalizar un CV', function () {
    Notification::fake();
    $cv = Cv::factory()->procesado()->create(['candidato_id' => $this->sofia->candidato_id, 'subido_por' => $this->marta->id]);

    CvAnalizado::dispatch($cv, primeraVez: false);

    Notification::assertNothingSent();
    expect(Actividad::count())->toBe(0);
});

it('nunca pone en un aviso un enlace de videollamada que no sea http o https', function () {
    $entrevista = Entrevista::factory()->create(['postulacion_id' => $this->sofia->id, 'enlace' => 'javascript:alert(1)']);

    expect(DatosDeEntrevista::de($entrevista)->enlace)->toBeNull();

    $entrevista->update(['enlace' => 'https://meet.example.com/abc']);

    expect(DatosDeEntrevista::de($entrevista->fresh())->enlace)->toBe('https://meet.example.com/abc');
});
