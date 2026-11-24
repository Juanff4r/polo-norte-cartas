<?php

use App\Http\Controllers\CartaController;
use Illuminate\Support\Facades\Route;

// Laravel antepone /api automáticamente a este archivo.

// Públicas
Route::post('/cartas', [CartaController::class, 'store']);
Route::get('/cartas', [CartaController::class, 'index']);
Route::get('/cartas/{id}', [CartaController::class, 'show'])->whereNumber('id');
Route::put('/cartas/{id}', [CartaController::class, 'update'])->whereNumber('id');

// Solo rol ELFO_JEFE (sin token -> 401, otro rol -> 403)
Route::middleware('rol:ELFO_JEFE')->group(function () {
    Route::delete('/cartas/{id}', [CartaController::class, 'destroy'])->whereNumber('id');
    Route::patch('/cartas/{id}/estado', [CartaController::class, 'cambiarEstado'])->whereNumber('id');
});
