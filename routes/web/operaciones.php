<?php

use App\Http\Controllers\Inventario\KardexController;
use App\Http\Controllers\ModulePageController;
use App\Http\Controllers\Operaciones\IngresoController;
use App\Http\Controllers\Operaciones\SalidaController;
use App\Http\Controllers\Operaciones\VentaController;
use Illuminate\Support\Facades\Route;

/* Operaciones que modifican dinero o existencias. */
Route::get('/ventas', [VentaController::class, 'index'])->middleware('permission:ventas.registrar')->name('ventas.index');
Route::get('/ventas/create', [VentaController::class, 'create'])->middleware('permission:ventas.registrar')->name('ventas.create');
Route::post('/ventas', [VentaController::class, 'store'])->middleware('permission:ventas.registrar')->name('ventas.store');
Route::post('/ventas/buscar-cliente', [VentaController::class, 'buscarYGuardarCliente'])->middleware('permission:ventas.registrar')->name('ventas.buscar-cliente');

Route::get('/ingresos', [IngresoController::class, 'index'])->middleware('permission:ingresos.registrar')->name('ingresos.index');
Route::get('/ingresos/create', [IngresoController::class, 'create'])->middleware('permission:ingresos.registrar')->name('ingresos.create');
Route::post('/ingresos', [IngresoController::class, 'store'])->middleware('permission:ingresos.registrar')->name('ingresos.store');

Route::get('/salidas', [SalidaController::class, 'index'])->middleware('permission:salidas.ver|salidas.registrar')->name('salidas.index');
Route::get('/salidas/create', [SalidaController::class, 'create'])->middleware('permission:salidas.registrar')->name('salidas.create');
Route::post('/salidas', [SalidaController::class, 'store'])->middleware('permission:salidas.registrar')->name('salidas.store');

Route::redirect('/inventario', '/kardex')->middleware('permission:inventario.ver')->name('inventario.index');
Route::get('/kardex', [KardexController::class, 'index'])->middleware('permission:kardex.ver')->name('kardex.index');
Route::post('/kardex/ajustar', [KardexController::class, 'ajustarStockYPrecio'])->middleware('permission:kardex.ver')->name('kardex.ajustar');
Route::get('/lotes', ModulePageController::class)->defaults('module', 'lotes')->middleware('permission:inventario.ver')->name('modules.lotes');
Route::get('/reportes', ModulePageController::class)->defaults('module', 'reportes')->middleware('permission:reportes.ver')->name('modules.reportes');
