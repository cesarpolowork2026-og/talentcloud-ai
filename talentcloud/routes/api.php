<?php

use App\Http\Controllers\Api\V1\SaludController;
use App\Http\Controllers\Api\V1\VacantesController;
use Illuminate\Support\Facades\Route;

/*
 * API REST pública y de solo lectura, versión 1 (/api/v1). Expone lo mismo que la bolsa pública de empleos: vacantes
 * abiertas de empresas activas, con sus datos públicos. Sin sesión ni cookies; límite de peticiones por IP.
 * Documentación: docs/API.md.
 */
Route::prefix('v1')->middleware('throttle:api')->name('api.v1.')->group(function (): void {
    Route::get('salud', SaludController::class)->name('salud');
    Route::get('vacantes', [VacantesController::class, 'index'])->name('vacantes.index');
    Route::get('vacantes/{id}', [VacantesController::class, 'show'])->whereNumber('id')->name('vacantes.show');
});
