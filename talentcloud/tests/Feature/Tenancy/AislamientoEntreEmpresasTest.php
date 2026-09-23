<?php

use App\Models\Empresa;
use App\Models\User;
use App\Tenancy\SinContextoDeEmpresa;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ContarRegistrosJob;
use Tests\Fixtures\RegistroDeEmpresa;

/*
 * Aislamiento entre empresas (plan §2): la empresa A no puede listar, ver,
 * editar ni borrar datos de la empresa B, ni por listados, ni por URL directa,
 * ni dentro de un Job.
 */

beforeEach(function () {
    RegistroDeEmpresa::crearTabla();

    $this->empresaA = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->empresaB = Empresa::factory()->create(['nombre' => 'Vela Studio']);

    app(TenantContext::class)->ejecutarGlobal(function () {
        $this->deA = RegistroDeEmpresa::create(['titulo' => 'Vacante de Nimbus', 'empresa_id' => $this->empresaA->id]);
        $this->deB = RegistroDeEmpresa::create(['titulo' => 'Vacante de Vela', 'empresa_id' => $this->empresaB->id]);
    });

    $this->reclutadorA = User::factory()->deEmpresa($this->empresaA)->reclutador()->create();

    // Rutas de prueba que imitan un CRUD real con route model binding.
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/_prueba/registros', fn () => RegistroDeEmpresa::orderBy('titulo')->pluck('titulo'));
        Route::get('/_prueba/registros/{registro}', fn (RegistroDeEmpresa $registro) => $registro->titulo);
        Route::put('/_prueba/registros/{registro}', fn (RegistroDeEmpresa $registro) => tap($registro)->update(['titulo' => 'Editado'])->titulo);
        Route::delete('/_prueba/registros/{registro}', fn (RegistroDeEmpresa $registro) => $registro->delete() ? 'ok' : 'no');
    });
});

it('lista solo los registros de la propia empresa', function () {
    $this->actingAs($this->reclutadorA)
        ->getJson('/_prueba/registros')
        ->assertOk()
        ->assertExactJson(['Vacante de Nimbus']);
});

it('no permite ver un registro de otra empresa por URL directa', function () {
    $this->actingAs($this->reclutadorA)->get('/_prueba/registros/'.$this->deA->id)->assertOk()->assertSee('Vacante de Nimbus');
    $this->actingAs($this->reclutadorA)->get('/_prueba/registros/'.$this->deB->id)->assertNotFound();
});

it('no permite editar un registro de otra empresa', function () {
    $this->actingAs($this->reclutadorA)->put('/_prueba/registros/'.$this->deB->id)->assertNotFound();

    expect(RegistroDeEmpresa::sinFiltroDeEmpresa()->find($this->deB->id)->titulo)->toBe('Vacante de Vela');
});

it('no permite borrar un registro de otra empresa', function () {
    $this->actingAs($this->reclutadorA)->delete('/_prueba/registros/'.$this->deB->id)->assertNotFound();

    expect(RegistroDeEmpresa::sinFiltroDeEmpresa()->whereKey($this->deB->id)->exists())->toBeTrue();
});

it('permite al admin SaaS ver los registros de todas las empresas', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/_prueba/registros')
        ->assertExactJson(['Vacante de Nimbus', 'Vacante de Vela']);
});

it('no muestra registros de empresa a un postulante', function () {
    $this->actingAs(User::factory()->postulante()->create())
        ->getJson('/_prueba/registros')
        ->assertExactJson([]);
});

it('devuelve una consulta vacía sin contexto de empresa (falla cerrado)', function () {
    app(TenantContext::class)->limpiar();

    expect(RegistroDeEmpresa::count())->toBe(0);
});

it('asigna la empresa del contexto al crear', function () {
    $registro = app(TenantContext::class)->ejecutarPara($this->empresaA->id, fn () => RegistroDeEmpresa::create(['titulo' => 'Nueva']));

    expect($registro->empresa_id)->toBe($this->empresaA->id);
});

it('impide crear registros sin empresa', function () {
    app(TenantContext::class)->limpiar();

    RegistroDeEmpresa::create(['titulo' => 'Huérfano']);
})->throws(SinContextoDeEmpresa::class);

it('impide crear registros para otra empresa desde el contexto de una', function () {
    app(TenantContext::class)->ejecutarPara(
        $this->empresaA->id,
        fn () => RegistroDeEmpresa::create(['titulo' => 'Intruso', 'empresa_id' => $this->empresaB->id]),
    );
})->throws(SinContextoDeEmpresa::class);

it('impide mover un registro a otra empresa', function () {
    app(TenantContext::class)->ejecutarGlobal(fn () => $this->deA->update(['empresa_id' => $this->empresaB->id]));
})->throws(SinContextoDeEmpresa::class);

it('ejecuta los Jobs con el contexto de la empresa de su payload', function () {
    app(TenantContext::class)->limpiar();

    ContarRegistrosJob::dispatchSync($this->empresaB->id);
    expect(ContarRegistrosJob::$vistos)->toBe(['Vacante de Vela']);

    ContarRegistrosJob::dispatchSync($this->empresaA->id);
    expect(ContarRegistrosJob::$vistos)->toBe(['Vacante de Nimbus']);

    // Al terminar el Job se restaura el contexto anterior (sin empresa).
    expect(app(TenantContext::class)->tieneEmpresa())->toBeFalse();
});

it('restaura el contexto anterior tras ejecutarPara', function () {
    $contexto = app(TenantContext::class);
    $contexto->establecer($this->empresaA->id);

    $contexto->ejecutarPara($this->empresaB->id, fn () => expect($contexto->id())->toBe($this->empresaB->id));

    expect($contexto->id())->toBe($this->empresaA->id);
});
