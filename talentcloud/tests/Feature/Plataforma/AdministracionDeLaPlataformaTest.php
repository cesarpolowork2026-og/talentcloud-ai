<?php

use App\Enums\EstadoVacante;
use App\Enums\PlanEmpresa;
use App\Livewire\Competencias\Listado as Competencias;
use App\Livewire\Dashboard\Panel;
use App\Livewire\Empresas\Listado as EmpresasListado;
use App\Livewire\Plataforma\Auditoria as RegistroDeAcciones;
use App\Livewire\Usuarios\Listado as Usuarios;
use App\Livewire\Vacantes\Detalle;
use App\Models\Auditoria;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Empresas\CambiarEstadoEmpresa;
use App\Services\Plataforma\PanelDePlataforma;
use App\Services\Plataforma\RegistroDeAuditoria;
use App\Tenancy\TenantContext;
use App\Tenancy\VerificarAcceso;
use Livewire\Livewire;

/*
 * Rol admin SaaS (Unidad 1): gestiona empresas, usuarios y el catálogo de competencias, deja constancia de lo que
 * hace y solo supervisa (lectura) los procesos de selección de las empresas.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->vela = Empresa::factory()->create(['nombre' => 'Vela Studio', 'plan' => PlanEmpresa::Pro]);
        $this->clara = User::factory()->deEmpresa($this->vela)->reclutador()->create(['name' => 'Clara Vidal', 'email' => 'clara@vela.test']);
        $this->vacante = Vacante::factory()->deEmpresa($this->vela)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Product Designer']);
    });
});

// — Usuarios —

it('lista a los usuarios de todas las empresas y filtra por rol y por texto', function () {
    actuarComo($this->admin);
    User::factory()->postulante()->create(['name' => 'Paola Postulante']);

    Livewire::test(Usuarios::class)
        ->assertSee('Clara Vidal')
        ->assertSee('Paola Postulante')
        ->set('rol', 'postulante')
        ->assertSee('Paola Postulante')
        ->assertDontSee('Clara Vidal')
        ->set('rol', '')
        ->set('busqueda', 'vela.test')
        ->assertSee('Clara Vidal')
        ->assertDontSee('Paola Postulante');
});

it('desactiva y reactiva al personal de una empresa y deja constancia', function () {
    actuarComo($this->admin);

    Livewire::test(Usuarios::class)
        ->call('confirmar', $this->clara->id)
        ->call('cambiarEstado')
        ->assertHasNoErrors();
    expect($this->clara->fresh()->estaActivo())->toBeFalse();

    Livewire::test(Usuarios::class)
        ->call('confirmar', $this->clara->id)
        ->call('cambiarEstado');
    expect($this->clara->fresh()->estaActivo())->toBeTrue()
        ->and(Auditoria::orderBy('id')->pluck('accion')->all())->toBe([RegistroDeAuditoria::USUARIO_DESACTIVADO, RegistroDeAuditoria::USUARIO_REACTIVADO]);
});

it('no deja desactivar a otro admin, a sí mismo ni a un postulante', function () {
    actuarComo($this->admin);
    $otroAdmin = User::factory()->admin()->create();
    $postulante = User::factory()->postulante()->create();

    foreach ([$otroAdmin, $this->admin, $postulante] as $objetivo) {
        Livewire::test(Usuarios::class)->call('confirmar', $objetivo->id)->assertForbidden();
    }
});

it('no deja entrar a Usuarios, Competencias ni al registro a quien no es admin', function () {
    foreach (['/usuarios', '/competencias', '/auditoria'] as $ruta) {
        $this->actingAs($this->clara)->get($ruta)->assertForbidden();
    }

    $this->actingAs($this->admin)->get('/usuarios')->assertOk();
});

// — Competencias —

it('crea, edita y elimina competencias del catálogo y lo anota en el registro', function () {
    actuarComo($this->admin);

    Livewire::test(Competencias::class)
        ->call('abrirFormulario')
        ->set('nombre', 'Terraform')
        ->set('categoria', 'Infraestructura')
        ->set('sinonimos', 'tf, IaC')
        ->call('guardar')
        ->assertHasNoErrors();

    $competencia = Competencia::where('nombre', 'Terraform')->firstOrFail();
    expect($competencia->sinonimos)->toBe(['tf', 'IaC']);

    Livewire::test(Competencias::class)
        ->call('abrirFormulario', $competencia->id)
        ->set('nombre', 'Terraform IaC')
        ->call('guardar')
        ->assertHasNoErrors();
    expect($competencia->fresh()->nombre)->toBe('Terraform IaC');

    Livewire::test(Competencias::class)
        ->call('confirmarEliminar', $competencia->id)
        ->call('eliminar')
        ->assertHasNoErrors();
    expect(Competencia::find($competencia->id))->toBeNull()
        ->and(Auditoria::orderBy('id')->pluck('accion')->all())->toBe([
            RegistroDeAuditoria::COMPETENCIA_CREADA,
            RegistroDeAuditoria::COMPETENCIA_EDITADA,
            RegistroDeAuditoria::COMPETENCIA_ELIMINADA,
        ]);
});

it('no repite nombres ni sinónimos del catálogo', function () {
    actuarComo($this->admin);
    Competencia::factory()->create(['nombre' => 'Docker']);

    Livewire::test(Competencias::class)
        ->call('abrirFormulario')
        ->set('nombre', 'docker')
        ->call('guardar')
        ->assertHasErrors('nombre');
});

it('no elimina una competencia en uso: se fusiona con otra sin perder los requisitos', function () {
    actuarComo($this->admin);
    $vieja = Competencia::factory()->create(['nombre' => 'K8s']);
    $nueva = Competencia::factory()->create(['nombre' => 'Kubernetes']);
    app(TenantContext::class)->ejecutarGlobal(fn () => Requisito::factory()->create(['vacante_id' => $this->vacante->id, 'competencia_id' => $vieja->id]));

    Livewire::test(Competencias::class)
        ->call('confirmarEliminar', $vieja->id)
        ->assertHasErrors('competencia');
    expect(Competencia::find($vieja->id))->not->toBeNull();

    Livewire::test(Competencias::class)
        ->call('abrirFusion', $vieja->id)
        ->set('destinoId', (string) $nueva->id)
        ->call('fusionar')
        ->assertHasNoErrors();

    expect(Competencia::find($vieja->id))->toBeNull()
        ->and(Requisito::sinFiltroDeEmpresa()->where('competencia_id', $nueva->id)->count())->toBe(1)
        // El nombre antiguo se sigue reconociendo como sinónimo.
        ->and($nueva->fresh()->sinonimos)->toContain('K8s');
});

// — Registro de acciones —

it('anota lo que el admin hace con las empresas', function () {
    actuarComo($this->admin);

    Livewire::test(EmpresasListado::class)
        ->call('confirmarCambioEstado', $this->vela->id)
        ->call('cambiarEstado');

    Livewire::test(EmpresasListado::class)
        ->call('abrirCambioDePlan', $this->vela->id)
        ->set('planNuevo', 'starter')
        ->call('cambiarPlan');

    $acciones = Auditoria::orderBy('id')->get();
    expect($acciones->pluck('accion')->all())->toBe([RegistroDeAuditoria::EMPRESA_SUSPENDIDA, RegistroDeAuditoria::PLAN_CAMBIADO])
        ->and($acciones[0]->usuario_nombre)->toBe('Ada Admin')
        ->and($acciones[0]->empresa_id)->toBe($this->vela->id);

    Livewire::test(RegistroDeAcciones::class)
        ->assertSee('Se suspendió la empresa Vela Studio.')
        ->assertSee('Ada Admin')
        ->set('busqueda', 'Starter')
        ->assertSee('Se cambió el plan de Vela Studio')
        ->assertDontSee('Se suspendió la empresa');
});

// — Panel —

it('el panel muestra ingresos estimados, crecimiento, estado del sistema y últimas acciones', function () {
    actuarComo($this->admin);
    app(RegistroDeAuditoria::class)->anotar($this->admin, RegistroDeAuditoria::EMPRESA_ACTIVADA, 'Se activó la empresa Vela Studio.', $this->vela->id);

    // Pro: S/ 349 al mes.
    Livewire::test(Panel::class)
        ->assertSee('Ingreso mensual de referencia')
        ->assertSee('S/ 349')
        ->assertSee('Postulaciones por semana')
        ->assertSee('Empresas nuevas por semana')
        ->assertSee('Estado del sistema')
        ->assertSee('Se activó la empresa Vela Studio.');
});

it('cuenta las postulaciones del mes y los trabajos fallidos del panel', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->create(['vacante_id' => $this->vacante->id]));
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    actuarComo($this->admin);
    $resumen = app(PanelDePlataforma::class)->resumen();

    expect($resumen->postulacionesDelMes)->toBe(1)
        ->and($resumen->trabajosFallidos)->toBe(1)
        ->and($resumen->sistemaSano())->toBeFalse()
        ->and($resumen->crecimiento)->toHaveCount(PanelDePlataforma::SEMANAS)
        ->and(array_sum(array_column($resumen->crecimiento, 'postulaciones')))->toBe(1);
});

// — Supervisión en solo lectura —

it('deja al admin ver las vacantes de las empresas pero no crearlas ni cambiarlas', function () {
    $this->actingAs($this->admin)
        ->get('/vacantes/'.$this->vacante->id)
        ->assertOk()
        ->assertSee('Product Designer');

    $this->actingAs($this->admin)->get('/vacantes')->assertOk()->assertSee('Supervisión · solo lectura');

    actuarComo($this->admin);
    Livewire::test(Detalle::class, ['vacante' => $this->vacante])
        ->call('cambiarEstado', 'cerrada')
        ->assertForbidden();
    expect($this->vacante->fresh()->estado)->toBe(EstadoVacante::Abierta);
});

it('no muestra el aviso de solo lectura al personal de una empresa', function () {
    $this->actingAs($this->clara)->get('/vacantes')->assertOk()->assertDontSee('Supervisión · solo lectura');
});

it('no deja al admin mover postulaciones ni leer las notas internas', function () {
    $postulacion = app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->create(['vacante_id' => $this->vacante->id]));

    expect($this->admin->can('view', $postulacion))->toBeTrue()
        ->and($this->admin->can('update', $postulacion))->toBeFalse()
        ->and($this->admin->can('gestionarNotas', $postulacion))->toBeFalse()
        ->and($this->clara->can('gestionarNotas', $postulacion))->toBeTrue();
});

it('abre el detalle de la empresa desde el enlace del panel', function () {
    actuarComo($this->admin);

    Livewire::withQueryParams(['ver' => $this->vela->id])
        ->test(EmpresasListado::class)
        ->assertSet('mostrarDetalle', true)
        ->assertSet('empresaSeleccionada', $this->vela->id);
});

it('saca de inmediato a quien pertenece a una empresa suspendida y le dice por qué', function () {
    $this->actingAs($this->clara)->get('/vacantes')->assertOk();

    app(CambiarEstadoEmpresa::class)->suspender($this->vela);

    $this->actingAs($this->clara->fresh())->get('/vacantes')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', VerificarAcceso::EMPRESA_SUSPENDIDA);
});
