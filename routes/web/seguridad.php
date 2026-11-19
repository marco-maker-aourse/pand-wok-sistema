<?php

use App\Http\Controllers\Seguridad\RolController;
use App\Http\Controllers\Seguridad\UsuarioController;
use App\Http\Controllers\Seguridad\UsuarioPageController;
use Illuminate\Support\Facades\Route;

Route::get('/usuarios', UsuarioPageController::class)->middleware('permission:usuarios.ver')->name('usuarios.index');
Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('permission:usuarios.gestionar')->name('usuarios.store');
Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permission:usuarios.gestionar')->name('usuarios.update');
Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->middleware('permission:usuarios.gestionar')->name('usuarios.destroy');

Route::get('/roles', [RolController::class, 'index'])->middleware('permission:usuarios.ver')->name('roles.index');
Route::post('/roles', [RolController::class, 'store'])->middleware('permission:usuarios.gestionar')->name('roles.store');
Route::put('/roles/{rol}', [RolController::class, 'update'])->middleware('permission:usuarios.gestionar')->name('roles.update');
Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->middleware('permission:usuarios.gestionar')->name('roles.destroy');
Route::put('/roles/{rol}/permisos', [UsuarioController::class, 'updatePermissions'])->middleware('permission:usuarios.gestionar')->name('roles.permissions.update');
