<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Livewire\Postulante\MisPostulaciones;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoGeneral;
use App\Tenancy\TenantContext;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('cvs');

    $this->marco = User::factory()->postulante()->create(['name' => 'Marco Díaz', 'password' => 'secreta-123']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Marco Díaz', 'user_id' => $this->marco->id]);

    // Postulaciones en dos empresas (el postulante no tiene contexto de empresa).
    [$this->backend, $this->devops] = app(TenantContext::class)->ejecutarGlobal(function () {
        $nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);

        $backend = Postulacion::factory()
            ->paraVacante(Vacante::factory()->deEmpresa($nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']))
            ->create([
                'candidato_id' => $this->candidato->id,
                'etapa' => EtapaPostulacion::EnRevision,
                'created_at' => '2026-09-02 10:00',
                'match_detalle' => ['requisitos' => [['nivel' => 'coincide'], ['nivel' => 'coincide'], ['nivel' => 'no_encontrado']]],
            ]);
        $devops = Postulacion::factory()
            ->paraVacante(Vacante::factory()->deEmpresa($vela)->enEstado(EstadoVacante::Cerrada)->create(['titulo' => 'DevOps Engineer']))
            ->create(['candidato_id' => $this->candidato->id, 'created_at' => '2026-08-20 10:00']);

        return [$backend, $devops];
    });

    $this->actingAs($this->marco);
});

// — Mis postulaciones —

it('muestra las postulaciones de todas las empresas como en el prototipo', function () {
    $this->get('/postulante/postulaciones')
        ->assertOk()
        ->assertSeeInOrder(['Vacante', 'Empresa', 'Estado', 'Fecha'])
        ->assertSeeInOrder(['Desarrollador Backend', 'Nimbus Corp', 'En revisión', '2 sep'])
        ->assertSeeInOrder(['DevOps Engineer', 'Vela Studio', 'Cerrada', '20 ago'])
        ->assertSee('Mi currículo')
        ->assertSee('Eliminar mi cuenta y mis datos');
});

it('muestra el detalle con las competencias que identificó la IA', function () {
    Livewire::test(MisPostulaciones::class)
        ->call('verDetalle', $this->backend->id)
        ->assertSet('mostrarDetalle', true)
        ->assertSee('Estado: En revisión')
        ->assertSee('Postulación enviada el 2 sep.')
        ->assertSee('La IA identificó en tu CV 2 de 3 competencias que pide la vacante.');
});

it('muestra un descarte en un proceso abierto como "No seleccionado"', function () {
    $this->backend->update(['etapa' => EtapaPostulacion::Descartado]);

    Livewire::test(MisPostulaciones::class)->assertSee('No seleccionado')->assertDontSee('Descartado');
});

it('muestra "No seleccionado" a quien se descartó, aunque después cierre la vacante', function () {
    // Es lo que se le avisó: la pantalla no puede decir otra cosa después.
    $this->devops->update(['etapa' => EtapaPostulacion::Descartado]);

    Livewire::test(MisPostulaciones::class)->assertSee('No seleccionado')->assertDontSeeHtml('>Cerrada<');
});

it('muestra "Cerrada" a quien seguía en el proceso cuando cerró la vacante', function () {
    $this->devops->update(['etapa' => EtapaPostulacion::EnRevision]);

    Livewire::test(MisPostulaciones::class)->assertSeeHtml('>Cerrada<');
});

it('no muestra la Oferta antes de que la empresa la comunique: sigue en "Entrevista"', function () {
    $this->backend->update(['etapa' => EtapaPostulacion::Oferta]);

    Livewire::test(MisPostulaciones::class)
        ->assertDontSee('Oferta')
        ->call('verDetalle', $this->backend->id)
        ->assertSee('Estado: Entrevista — Estás en la etapa de entrevistas.');
});

it('no abre postulaciones de otra persona', function () {
    $ajena = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->create());

    Livewire::test(MisPostulaciones::class)->call('verDetalle', $ajena->id)->assertNotFound();
});

it('no deja entrar al personal de selección', function () {
    $this->actingAs(User::factory()->reclutador()->create())->get('/postulante/postulaciones')->assertForbidden();
});

// — Eliminar mi cuenta y mis datos —

it('elimina la cuenta con todos sus datos y archivos', function () {
    Storage::disk('cvs')->put('candidatos/marco.pdf', 'contenido');
    Cv::factory()->procesado()->create(['candidato_id' => $this->candidato->id, 'disco' => 'cvs', 'ruta' => 'candidatos/marco.pdf']);
    $this->marco->notify(new AvisoGeneral('Tu CV fue analizado.'));
    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->entrevista = Entrevista::factory()->create(['postulacion_id' => $this->backend->id, 'inicia_at' => '2026-12-01 10:00']);
        $actividad = new Actividad(['tipo' => 'postulacion.etapa', 'descripcion' => 'Marco Díaz avanzó a la etapa de entrevista.']);
        $actividad->empresa_id = $this->backend->empresa_id;
        $actividad->sujeto()->associate($this->backend);
        $actividad->save();
    });
    $otro = Candidato::factory()->create(); // no se toca

    Volt::test('profile.delete-user-form')->set('password', 'secreta-123')->call('deleteUser')->assertRedirect(route('login'));

    expect(User::find($this->marco->id))->toBeNull()
        ->and(Candidato::find($this->candidato->id))->toBeNull()
        ->and(Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $this->candidato->id)->exists())->toBeFalse()
        ->and(Entrevista::sinFiltroDeEmpresa()->count())->toBe(0)
        ->and(Actividad::sinFiltroDeEmpresa()->where('descripcion', 'like', '%Marco Díaz%')->exists())->toBeFalse()
        // Solo queda el aviso al entrevistador de que su entrevista se canceló, sin datos de la persona.
        ->and(DatabaseNotification::pluck('data')->map(fn ($d) => is_array($d) ? $d['texto'] : json_decode($d, true)['texto'])->all())
        ->toBe(['Se canceló tu entrevista del 1 dic · 10:00 para Desarrollador Backend: la persona candidata eliminó su cuenta.'])
        ->and(DatabaseNotification::first()->notifiable_id)->toBe($this->entrevista->entrevistador_id)
        ->and(Candidato::find($otro->id))->not->toBeNull();
    Storage::disk('cvs')->assertMissing('candidatos/marco.pdf');
    $this->assertGuest();
});

it('pide la contraseña para eliminar la cuenta', function () {
    Volt::test('profile.delete-user-form')->set('password', 'incorrecta')->call('deleteUser')->assertHasErrors(['password']);

    expect(User::find($this->marco->id))->not->toBeNull();
});

// — Experiencia del postulante —

it('explica en el detalle qué significa el estado de la postulación', function () {
    actuarComo($this->marco);

    Livewire::test(MisPostulaciones::class)
        ->call('verDetalle', $this->backend->id)
        ->assertSee('Estado: En revisión — La empresa está revisando tu perfil.')
        ->call('verDetalle', $this->devops->id)
        ->assertSee('Estado: Cerrada — La vacante cerró y el proceso terminó.');
});

it('deja descargar su propio CV desde Mi currículo', function () {
    $cv = Cv::factory()->procesado()->create(['candidato_id' => $this->candidato->id, 'nombre_original' => 'CV_MarcoDiaz.pdf']);

    $this->get('/postulante/postulaciones')
        ->assertOk()
        ->assertSee('Descargar mi CV')
        ->assertSee(route('cvs.descargar', $cv), false);
});
