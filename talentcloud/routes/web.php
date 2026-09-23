<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

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
