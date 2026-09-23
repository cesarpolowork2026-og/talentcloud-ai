<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Auth\AceptarInvitacion;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    // "Crear empresa" del login.
    Volt::route('register', 'pages.auth.register')
        ->name('register');

    Volt::route('registro/postulante', 'pages.auth.register-postulante')
        ->name('register.postulante');

    // Enlace del correo de invitación a un equipo.
    Route::get('invitacion/{token}', AceptarInvitacion::class)
        ->middleware('throttle:20,1')
        ->name('invitacion.aceptar');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
