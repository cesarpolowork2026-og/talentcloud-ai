<?php

use App\Http\Controllers\AvisoDePagoController;
use App\Http\Controllers\DescargarCalendarioDeEntrevistaController;
use App\Http\Controllers\DescargarCvController;
use App\Http\Controllers\DescargarMisDatosController;
use App\Http\Controllers\PostularDesdeEmpleosController;
use App\Livewire\Candidatos;
use App\Livewire\Dashboard;
use App\Livewire\Empleos;
use App\Livewire\Empresas;
use App\Livewire\Entrevistas;
use App\Livewire\Evaluaciones;
use App\Livewire\Legal;
use App\Livewire\Matching;
use App\Livewire\MiEmpresa;
use App\Livewire\Onboarding;
use App\Livewire\Planes;
use App\Livewire\Plataforma;
use App\Livewire\Postulante;
use App\Livewire\Reclamaciones;
use App\Livewire\Seleccion;
use App\Livewire\Vacantes;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Volt\Volt;

/*
 * Rutas por rol según la matriz de permisos del plan (sección 3), tomada del
 * prototipo. Un rol sin acceso recibe la página 403 "Acceso restringido".
 * El filtro por empresa de los datos lo aplica el TenantContext (grupo web).
 */

// Presentación con el login en el hero (layouts.presentacion). Con sesión iniciada,
// el middleware guest lleva a la pantalla de inicio del rol (bootstrap/app.php).
Volt::route('/', 'pages.auth.login')->middleware('guest')->name('inicio');

// Bolsa pública de empleos (ampliación): vacantes abiertas, sin iniciar sesión y sin model
// binding (no hay contexto de empresa). Solo VacanteRepository::publicadas().
Route::get('empleos', Empleos\Listado::class)->name('empleos.index');
Route::get('empleos/{id}', Empleos\Detalle::class)->whereNumber('id')->name('empleos.show');
Route::get('empleos/{id}/postular', PostularDesdeEmpleosController::class)->whereNumber('id')->name('empleos.postular');

// Planes y precios (ampliación): página pública con los precios de PlanEmpresa.
Route::get('planes', Planes\Precios::class)->name('planes');

// Política de privacidad (Ley 29733) y términos de uso: públicas, enlazadas desde el pie y el registro.
Route::get('privacidad', Legal\Privacidad::class)->name('privacidad');
Route::get('terminos', Legal\Terminos::class)->name('terminos');

// Libro de Reclamaciones virtual (Ley 29571): público; las hojas las atiende el admin SaaS en /reclamaciones.
Route::get('libro-de-reclamaciones', Reclamaciones\Formulario::class)->name('reclamaciones.crear');

// Avisos de la pasarela de pago (Stripe): sin sesión ni CSRF, autenticados por su firma. Sin la sesión,
// cada aviso no deja una fila en la tabla de sesiones.
Route::post('stripe/webhook', AvisoDePagoController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, AuthenticateSession::class, ValidateCsrfToken::class])
    ->name('pagos.aviso');

Route::middleware(['auth', 'verified'])->group(function () {
    // Personal de selección: admin SaaS, empresa y reclutador.
    Route::middleware('rol:admin|empresa|reclutador')->group(function () {
        Route::get('dashboard', Dashboard\Panel::class)->name('dashboard');

        Route::get('vacantes', Vacantes\Listado::class)->name('vacantes.index');
        Route::get('vacantes/{vacante}', Vacantes\Detalle::class)->name('vacantes.show');

        // «Selección»: el tablero (Kanban) y el ranking por match de las postulaciones de una vacante.
        Route::get('seleccion', Seleccion\Central::class)->name('seleccion.index');
        Route::get('seleccion/{vacante}', Seleccion\Central::class)->name('seleccion.show');

        // El matching es el detalle explicable de una postulación; sin ella, lleva al ranking.
        Route::redirect('matching', '/seleccion?vista=ranking')->name('matching.index');
        Route::get('matching/{postulacion}', Matching\Detalle::class)->name('matching.show');

        // Direcciones anteriores del Kanban: siguen funcionando (enlaces guardados, correos ya enviados).
        Route::get('kanban', Seleccion\Central::class)->name('kanban.index');
        Route::get('vacantes/{vacante}/kanban', Seleccion\Central::class)->name('kanban.show');

        Route::get('entrevistas', Entrevistas\Listado::class)->name('entrevistas.index');
        Route::get('entrevistas/{entrevista}/calendario', DescargarCalendarioDeEntrevistaController::class)->name('entrevistas.calendario');
        Route::get('evaluaciones', Evaluaciones\Listado::class)->name('evaluaciones.index');

        Route::get('candidatos', Candidatos\Listado::class)->name('candidatos.index');
        Route::get('candidatos/{candidato}', Candidatos\Detalle::class)->name('candidatos.show');
    });

    // Admin SaaS.
    Route::middleware('rol:admin')->group(function () {
        Route::get('empresas', Empresas\Listado::class)->name('empresas.index');
        Route::get('calidad-ia', Plataforma\CalidadIa::class)->name('calidad-ia');
        Route::get('reclamaciones', Reclamaciones\Listado::class)->name('reclamaciones.index');
    });

    // Cuenta de empresa.
    Route::middleware('rol:empresa')->group(function () {
        Route::get('mi-empresa', MiEmpresa\Panel::class)->name('mi-empresa');
        Route::get('bienvenida', Onboarding\Checklist::class)->name('onboarding');
    });

    // Postulante.
    Route::middleware('rol:postulante')->prefix('postulante')->name('postulante.')->group(function () {
        Route::get('postulaciones', Postulante\MisPostulaciones::class)->name('postulaciones');
        // Vacantes publicadas de todas las empresas (ampliación sobre el prototipo). Sin model
        // binding: el postulante no tiene contexto de empresa y la vacante se lee por su id.
        Route::get('vacantes', Postulante\Vacantes::class)->name('vacantes');
        Route::get('vacantes/{id}', Postulante\DetalleVacante::class)->whereNumber('id')->name('vacantes.show');
        // Derecho de acceso: JSON con sus propios datos, nunca los internos de las empresas.
        Route::get('mis-datos', DescargarMisDatosController::class)->middleware('throttle:10,1')->name('mis-datos');
    });
});

// Descarga autorizada de CVs (personal de la empresa o el propio postulante).
Route::get('cvs/{cv}/descargar', DescargarCvController::class)
    ->middleware(['auth', 'verified'])
    ->name('cvs.descargar');

// Mi perfil: todos los roles.
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Catálogo de componentes del design system, solo en desarrollo.
if (app()->environment('local')) {
    Route::view('_ui', 'dev.ui')->name('dev.ui');
}

require __DIR__.'/auth.php';

// Las rutas inexistentes pasan por el middleware web para que la 404 reconozca la sesión.
Route::fallback(fn () => abort(404));
