<?php

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Livewire\Postulante\DetalleVacante;
use App\Livewire\Postulante\MiCurriculo;
use App\Livewire\Postulante\MisPostulaciones;
use App\Livewire\Postulante\Vacantes;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoNuevaPostulacion;
use App\Repositories\Contracts\CandidatoRepository;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Ampliación sobre el prototipo: el postulante ve las vacantes abiertas de las
 * empresas activas y postula con su CV. La postulación sigue el camino de la
 * de un reclutador (Kanban, matching) y avisa a la empresa.
 */

beforeEach(function () {
    Queue::fake(); // el matching se programa, no se ejecuta

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $orbita = Empresa::factory()->suspendida()->create(['nombre' => 'Orbita Labs']);

        $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
        $this->cuentaNimbus = User::factory()->deEmpresa($this->nimbus)->empresa()->create();
        $this->diego = User::factory()->deEmpresa($this->nimbus)->reclutador()->create();
        $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create();

        $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)
            ->create(['titulo' => 'Desarrollador Backend', 'descripcion' => 'APIs REST de alto tráfico.', 'creada_por' => $this->marta->id, 'ubicacion' => 'Remoto', 'modalidad' => Modalidad::Remoto]);
        Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Java')->id, 'anios_minimos' => 3]);
        Requisito::factory()->create(['vacante_id' => $this->backend->id, 'competencia_id' => Competencia::obtenerOCrear('Docker')->id, 'tipo' => TipoRequisito::Deseable]);

        $this->disenador = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)
            ->create(['titulo' => 'Product Designer', 'ubicacion' => 'Lima', 'modalidad' => Modalidad::Presencial]);

        $this->noPublicas = [
            'borrador' => Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Borrador)->create(['titulo' => 'QA Engineer']),
            'en revisión' => Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::EnRevision)->create(['titulo' => 'Data Analyst']),
            'cerrada' => Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'DevOps Engineer']),
            'empresa suspendida' => Vacante::factory()->deEmpresa($orbita)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Tester Orbita']),
        ];
    });

    $this->marco = User::factory()->postulante()->create(['name' => 'Marco Díaz']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marco->id]);
    $this->cv = Cv::factory()->procesado()->create(['candidato_id' => $this->candidato->id, 'nombre_original' => 'CV_MarcoDiaz.pdf']);

    actuarComo($this->marco);
});

// — Listado —

it('muestra solo las vacantes abiertas de empresas activas', function () {
    $pantalla = Livewire::test(Vacantes::class)->assertSee('Desarrollador Backend')->assertSee('Product Designer');

    foreach (['QA Engineer', 'Data Analyst', 'DevOps Engineer', 'Tester Orbita'] as $titulo) {
        $pantalla->assertDontSee($titulo);
    }
});

it('muestra la pantalla con la navegación del postulante', function () {
    $this->get('/postulante/vacantes')
        ->assertOk()
        ->assertSee('Ofertas abiertas de las empresas en TalentCloud')
        ->assertSee(route('postulante.vacantes'), escape: false)
        ->assertSee('Java'); // requisitos obligatorios en la tarjeta
});

it('busca sin distinguir tildes y filtra por modalidad', function () {
    Livewire::test(Vacantes::class)->set('busqueda', 'LIMA')
        ->assertSee('Product Designer')->assertDontSee('Desarrollador Backend');

    Livewire::test(Vacantes::class)->set('busqueda', 'nimbus')
        ->assertSee('Desarrollador Backend')->assertDontSee('Product Designer');

    Livewire::test(Vacantes::class)->call('filtrarEstado', Modalidad::Presencial->value)
        ->assertSee('Product Designer')->assertDontSee('Desarrollador Backend');
});

it('marca las vacantes a las que ya postuló', function () {
    app(PostularmeAVacante::class)->ejecutar($this->marco, $this->backend->id);

    Livewire::test(Vacantes::class)
        ->assertSeeHtml('Ya postulaste')
        ->assertSeeHtml('aria-label="Ver y postular a Product Designer"')
        ->assertDontSeeHtml('aria-label="Ver y postular a Desarrollador Backend"');
});

// — Detalle —

it('muestra el detalle con los requisitos, sin datos de otros candidatos', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)
        ->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Elena Ruiz'])->id]));

    $this->get('/postulante/vacantes/'.$this->backend->id)
        ->assertOk()
        ->assertSee(['Desarrollador Backend', 'Nimbus Corp', 'APIs REST de alto tráfico.'])
        ->assertSeeInOrder(['Java', 'Obligatorio', '3 años de experiencia', 'Docker', 'Deseable'])
        ->assertSee('CV_MarcoDiaz.pdf')
        ->assertSee('Postular con mi CV')
        ->assertDontSee('Elena Ruiz');
});

it('da 404 en el detalle de una vacante que no es pública', function (string $cual) {
    $this->get('/postulante/vacantes/'.$this->noPublicas[$cual]->id)->assertNotFound();
})->with(['borrador', 'en revisión', 'cerrada', 'empresa suspendida']);

it('da 404 en el detalle de una vacante inexistente', function () {
    $this->get('/postulante/vacantes/999999')->assertNotFound();
});

// — Postular —

it('postula con su CV: entra en el Kanban de la empresa y se programa el matching', function () {
    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->call('confirmar')
        ->assertSet('mostrarConfirmacion', true)
        ->call('postular')
        ->assertHasNoErrors()
        ->assertSet('postulado', true)
        ->assertSee('¡Listo! Te postulaste a Desarrollador Backend.');

    $postulacion = Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $this->candidato->id)->sole();

    expect($postulacion->empresa_id)->toBe($this->nimbus->id)
        ->and($postulacion->vacante_id)->toBe($this->backend->id)
        ->and($postulacion->etapa)->toBe(EtapaPostulacion::Postulado)
        ->and($postulacion->cv_id)->toBe($this->cv->id);

    Queue::assertPushed(CalcularMatch::class, fn (CalcularMatch $job) => $job->postulacionId === $postulacion->id && $job->empresaId === $this->nimbus->id);
});

it('hace visible al candidato para la empresa a la que postuló y solo para ella', function () {
    app(PostularmeAVacante::class)->ejecutar($this->marco, $this->backend->id);

    $candidatos = app(CandidatoRepository::class);

    expect($candidatos->esVisibleParaEmpresa($this->candidato, $this->nimbus->id))->toBeTrue()
        ->and($candidatos->esVisibleParaEmpresa($this->candidato, $this->vela->id))->toBeFalse();
});

it('avisa a quien creó la vacante y a la cuenta de empresa, y lo anota en la actividad', function () {
    Notification::fake();

    app(PostularmeAVacante::class)->ejecutar($this->marco, $this->backend->id);

    Notification::assertSentTo([$this->marta, $this->cuentaNimbus], AvisoNuevaPostulacion::class,
        fn (AvisoNuevaPostulacion $aviso) => $aviso->texto === 'Marco Díaz postuló a Desarrollador Backend.');
    Notification::assertNotSentTo([$this->diego, $this->clara, $this->marco], AvisoNuevaPostulacion::class);

    $actividad = app(TenantContext::class)->ejecutarPara($this->nimbus->id, fn () => Actividad::latest('id')->first());
    expect($actividad->descripcion)->toBe('Marco Díaz postuló a Desarrollador Backend.')
        ->and($actividad->actor_id)->toBe($this->marco->id);
});

it('no avisa a nadie desactivado', function () {
    Notification::fake();
    $this->marta->forceFill(['desactivado_at' => now()])->save();

    app(PostularmeAVacante::class)->ejecutar($this->marco, $this->backend->id);

    Notification::assertNotSentTo($this->marta, AvisoNuevaPostulacion::class);
    Notification::assertSentTo($this->cuentaNimbus, AvisoNuevaPostulacion::class);
});

it('no genera el aviso de postulación propia cuando postula un reclutador', function () {
    Notification::fake();
    actuarComo($this->marta);

    app(PostularCandidato::class)->ejecutar($this->backend, Candidato::factory()->create());

    Notification::assertNothingSentTo([$this->marta, $this->cuentaNimbus]);
});

it('no deja postular dos veces a la misma vacante', function () {
    app(PostularmeAVacante::class)->ejecutar($this->marco, $this->backend->id);

    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->assertSee('Ya postulaste')
        ->call('postular')
        ->assertHasErrors(['postulacion' => 'Ya postulaste a esta vacante.']);

    expect(Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $this->candidato->id)->count())->toBe(1);
});

it('exige un CV que la IA haya podido leer', function (string $situacion, string $mensaje) {
    $this->cv->delete();
    match ($situacion) {
        'ilegible' => Cv::factory()->conError()->create(['candidato_id' => $this->candidato->id]),
        'en análisis' => Cv::factory()->create(['candidato_id' => $this->candidato->id]), // pendiente
        default => null, // sin CV
    };

    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->call('postular')
        ->assertHasErrors(['postulacion' => $mensaje]);

    expect(Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $this->candidato->id)->exists())->toBeFalse();
})->with([
    'sin CV' => ['sin CV', 'Sube tu CV en "Mi currículo" para poder postular.'],
    'CV ilegible' => ['ilegible', 'Tu CV no se pudo leer. Sube una versión en PDF con texto o en Word para postular.'],
    'CV en análisis' => ['en análisis', 'Tu CV todavía se está analizando. Inténtalo en unos segundos.'],
]);

it('no deja postular si la vacante deja de ser pública mientras la mira', function (string $cambio) {
    $pantalla = Livewire::test(DetalleVacante::class, ['id' => $this->backend->id]);

    app(TenantContext::class)->ejecutarGlobal(fn () => match ($cambio) {
        'se cerró' => $this->backend->update(['estado' => EstadoVacante::Cerrada]),
        'su empresa fue suspendida' => $this->nimbus->update(['estado' => EstadoEmpresa::Suspendida]),
    });

    $pantalla->call('postular')
        ->assertHasErrors(['postulacion' => 'Esta vacante ya no recibe postulaciones.'])
        ->assertSee('Esta vacante ya no recibe postulaciones.');

    expect(Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $this->candidato->id)->exists())->toBeFalse();
})->with(['se cerró', 'su empresa fue suspendida']);

it('limita las postulaciones por hora', function () {
    foreach (range(1, PostularmeAVacante::MAXIMO_POR_HORA) as $i) {
        RateLimiter::hit('postular|'.$this->marco->id, 3600);
    }

    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->call('postular')
        ->assertHasErrors(['postulacion' => 'Alcanzaste el máximo de postulaciones por hora. Inténtalo más tarde.']);
});

// — Acceso —

it('niega las vacantes del postulante al personal de selección', function () {
    actuarComo($this->marta);

    $this->get('/postulante/vacantes')->assertForbidden();
    $this->get('/postulante/vacantes/'.$this->backend->id)->assertForbidden();
    Livewire::test(Vacantes::class)->assertForbidden();
});

it('invita a ver vacantes cuando todavía no tiene postulaciones', function () {
    Livewire::test(MisPostulaciones::class)
        ->assertSee('Todavía no tienes postulaciones')
        ->assertSee('Ver vacantes abiertas');
});

it('no repite la ubicación cuando coincide con la modalidad', function (?string $ubicacion, string $esperado) {
    $vacante = new Vacante(['ubicacion' => $ubicacion, 'modalidad' => Modalidad::Remoto]);

    expect($vacante->lugarYModalidad())->toBe($esperado);
})->with([
    'ciudad' => ['Madrid', 'Madrid · Remoto'],
    'repetida' => ['remoto', 'Remoto'],
    'sin ubicación' => [null, 'Remoto'],
]);

// — Experiencia del postulante —

it('marca los requisitos de la tarjeta como un grupo con nombre accesible', function () {
    Livewire::test(Vacantes::class)->assertSeeHtml('role="group" aria-label="Requisitos obligatorios"');
});

it('sube el CV desde la propia vacante y la página espera su análisis para postular', function () {
    Storage::fake('cvs');
    $this->cv->delete();

    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->assertSee('Para postular necesitas subir tu CV.')
        ->assertSeeLivewire(MiCurriculo::class)
        ->assertDontSee('Postular con mi CV');

    Livewire::test(MiCurriculo::class, ['soloSubida' => true])
        ->assertSee('Subir mi CV')
        ->set('archivoCv', UploadedFile::fake()->createWithContent('CV_MarcoDiaz.pdf', DocumentosDePrueba::pdf('Java y Spring Boot.')))
        ->call('subir')
        ->assertHasNoErrors()
        ->assertDispatched('cv-subido');

    // Con la cola simulada, el CV queda pendiente de análisis: la vacante lo indica y espera.
    Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])
        ->assertSee('Tu CV se está analizando. Podrás postular en unos segundos.');
});

it('si la vacante a la que postuló cierra mientras la mira, lleva a sus postulaciones', function () {
    $pantalla = Livewire::test(DetalleVacante::class, ['id' => $this->backend->id])->call('confirmar')->call('postular');
    app(TenantContext::class)->ejecutarGlobal(fn () => $this->backend->update(['estado' => EstadoVacante::Cerrada]));

    $pantalla->call('$refresh')
        ->assertSee('Esta vacante ya no recibe postulaciones.')
        ->assertSee('Tu postulación sigue en «Mis postulaciones», con su estado.')
        ->assertSee('Ver mis postulaciones');
});
