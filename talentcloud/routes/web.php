<?php

use App\Support\Navegacion;
use Illuminate\Support\Facades\Route;

// Sin página pública: la raíz lleva al panel del usuario o al login.
Route::get('/', fn () => redirect(Navegacion::rutaInicio(auth()->user())))->name('inicio');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
