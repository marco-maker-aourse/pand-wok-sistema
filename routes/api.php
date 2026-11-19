<?php

use App\Http\Controllers\Api\AgenteImpresionController;
use App\Http\Controllers\Api\CartaPublicaController;
use Illuminate\Support\Facades\Route;

// Carta digital pública: solo lectura, sin sesión. CORS abierto (config por defecto de api/*).
Route::middleware('throttle:120,1')->group(function (): void {
    Route::get('/carta-publica', [CartaPublicaController::class, 'index'])->name('api.carta-publica');
    Route::get('/carta-publica/{slug}', [CartaPublicaController::class, 'sucursal'])
        ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*')
        ->name('api.carta-publica.sucursal');
});

// API exclusiva del agente local; no reutiliza la sesión del navegador ni CSRF.
Route::prefix('agente-impresion/v1')->group(function (): void {
    Route::post('/vinculaciones', [AgenteImpresionController::class, 'vincular'])->middleware('throttle:5,1');
    Route::middleware(['print-agent', 'throttle:60,1'])->group(function (): void {
        Route::get('/trabajos/siguiente', [AgenteImpresionController::class, 'siguiente']);
        Route::post('/trabajos/{trabajo}/resultado', [AgenteImpresionController::class, 'resultado']);
        Route::put('/terminal/impresora-sistema', [AgenteImpresionController::class, 'configurarImpresoraSistema']);
    });
});
