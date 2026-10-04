<?php

use App\Models\Empresa;
use App\Models\User;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Route;

/*
 * La barra de navegación reproduce la del prototipo para cada rol.
 */

function etiquetasDe(User $user): array
{
    return array_column(Navegacion::enlaces($user), 'etiqueta');
}

$personal = ['Dashboard', 'Vacantes', 'Kanban', 'Candidatos', 'Matching', 'Entrevistas', 'Evaluaciones'];

it('muestra al reclutador los módulos de selección', function () use ($personal) {
    expect(etiquetasDe(User::factory()->reclutador()->create()))->toBe($personal);
});

it('ordena el menú del admin: primero lo suyo y la selección agrupada', function () use ($personal) {
    $enlaces = Navegacion::enlaces(User::factory()->admin()->create());

    expect(array_column($enlaces, 'etiqueta'))->toBe(['Panel', 'Empresas', 'Calidad de la IA', 'Procesos de selección'])
        ->and($enlaces[0]['ruta'])->toBe('dashboard')
        ->and($enlaces[3]['descripcion'])->toBe('Supervisión de las empresas')
        // Las mismas pantallas que el reclutador, sin su Dashboard (para el admin es "Panel").
        ->and(array_column($enlaces[3]['hijos'], 'etiqueta'))->toBe(array_slice($personal, 1));
});

it('añade Mi empresa a la empresa', function () use ($personal) {
    expect(etiquetasDe(User::factory()->empresa()->create()))->toBe([...$personal, 'Mi empresa']);
});

it('muestra al postulante sus postulaciones y las vacantes abiertas', function () {
    expect(etiquetasDe(User::factory()->postulante()->create()))->toBe(['Mis postulaciones', 'Vacantes']);
});

it('no muestra enlaces a una cuenta sin rol', function () {
    expect(Navegacion::enlaces(User::factory()->create()))->toBe([]);
});

it('usa la etiqueta e inicial de perfil del prototipo', function () {
    $admin = User::factory()->admin()->create();
    $postulante = User::factory()->postulante()->create(['name' => 'Marco Díaz']);

    expect(Navegacion::etiquetaPerfil($admin))->toBe('Administrador SaaS')
        ->and(Navegacion::inicial($admin))->toBe('A')
        ->and(Navegacion::etiquetaPerfil($postulante))->toBe('Postulante · Marco Díaz')
        ->and(Navegacion::inicial($postulante))->toBe('M');
});

it('muestra la empresa en la etiqueta de perfil de empresa y reclutador', function () {
    $nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);

    expect(Navegacion::etiquetaPerfil(User::factory()->deEmpresa($nimbus)->reclutador()->create()))->toBe('Reclutador · Nimbus Corp')
        ->and(Navegacion::etiquetaPerfil(User::factory()->deEmpresa($nimbus)->empresa()->create()))->toBe('Empresa · Nimbus Corp');
});

it('renderiza la barra con la marca, los enlaces y el menú de perfil', function () {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['TalentCloud', 'AI', 'Dashboard', 'Vacantes', 'Evaluaciones'])
        ->assertSee('Reclutador')
        ->assertSee('Mi perfil')
        ->assertSee('Cerrar sesión')
        ->assertSee('aria-current="page"', false);
});

it('pinta el desplegable "Procesos de selección" del admin y lo marca activo dentro de él', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Panel', 'Empresas', 'Calidad de la IA', 'Procesos de selección', 'Supervisión de las empresas', 'Vacantes', 'Evaluaciones'])
        ->assertDontSee('aria-current="true"', false)
        // Disclosure de navegación, no un menú de aplicación (W3C APG).
        ->assertSee('aria-controls="grupo-escritorio-3"', false)
        ->assertSee('id="grupo-escritorio-3"', false)
        ->assertDontSee('aria-haspopup', false);

    $this->actingAs($admin)
        ->get('/vacantes')
        ->assertOk()
        ->assertSee('aria-current="true"', false);
});

it('mantiene el menú de perfil del admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Administrador SaaS')
        ->assertSee('Mi perfil')
        ->assertSee('Cerrar sesión')
        ->assertSee('aria-current="page"', false);
});

it('muestra la página 404 del prototipo con la sesión del usuario', function () {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertSee('Página no encontrada')
        ->assertSee('La sección que buscas no existe o fue movida.')
        ->assertSee('Volver a mi panel');
});

it('muestra la página 403 del prototipo', function () {
    Route::middleware('web')->get('/_prueba-403', fn () => abort(403));

    $this->actingAs(User::factory()->reclutador()->create())
        ->get('/_prueba-403')
        ->assertForbidden()
        ->assertSee('Acceso restringido')
        ->assertSee('No tienes permisos para ver esta sección con tu rol actual.');
});

it('muestra la 404 a un invitado con acceso al login', function () {
    $this->get('/ruta-que-no-existe')
        ->assertNotFound()
        ->assertSee('Ir a iniciar sesión');
});
