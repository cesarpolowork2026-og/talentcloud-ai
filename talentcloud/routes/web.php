<?php

use App\Livewire\Empresas;
use App\Livewire\MiEmpresa;
use App\Livewire\Onboarding;
use App\Livewire\Vacantes;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Route;

/*
 * Rutas por rol según la matriz de permisos del plan (sección 3), tomada del
 * prototipo. Un rol sin acceso recibe la página 403 "Acceso restringido".
 * El filtro por empresa de los datos lo aplica el TenantContext (grupo web).
 */

// Sin página pública: la raíz lleva al panel del usuario o al login.
Route::get('/', fn () => redirect(Navegacion::rutaInicio(auth()->user())))->name('inicio');

Route::middleware(['auth', 'verified'])->group(function () {
    // Personal de selección: admin SaaS, empresa y reclutador.
    Route::middleware('rol:admin|empresa|reclutador')->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::get('vacantes', Vacantes\Listado::class)->name('vacantes.index');
        Route::get('vacantes/{vacante}', Vacantes\Detalle::class)->name('vacantes.show');
    });

    // Admin SaaS.
    Route::middleware('rol:admin')->group(function () {
        Route::get('empresas', Empresas\Listado::class)->name('empresas.index');
    });

    // Cuenta de empresa.
    Route::middleware('rol:empresa')->group(function () {
        Route::get('mi-empresa', MiEmpresa\Panel::class)->name('mi-empresa');
        Route::get('bienvenida', Onboarding\Checklist::class)->name('onboarding');
    });

    // Postulante.
    Route::middleware('rol:postulante')->prefix('postulante')->name('postulante.')->group(function () {
        Route::view('postulaciones', 'postulante.postulaciones')->name('postulaciones');
    });
});

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
