<?php

use App\Http\Controllers\Api\AgenteImpresionController;
use Illuminate\Support\Facades\Route;

// API exclusiva del agente local; no reutiliza la sesión del navegador ni CSRF.
Route::prefix('agente-impresion/v1')->group(function (): void {
    Route::post('/vinculaciones', [AgenteImpresionController::class, 'vincular'])->middleware('throttle:5,1');
    Route::middleware(['print-agent', 'throttle:60,1'])->group(function (): void {
        Route::get('/trabajos/siguiente', [AgenteImpresionController::class, 'siguiente']);
        Route::post('/trabajos/{trabajo}/resultado', [AgenteImpresionController::class, 'resultado']);
        Route::put('/terminal/impresora-sistema', [AgenteImpresionController::class, 'configurarImpresoraSistema']);
    });
});
