<?php

use App\Http\Controllers\Catalogos\CategoriaController;
use App\Http\Controllers\Catalogos\ClienteController;
use App\Http\Controllers\Catalogos\MarcaController;
use App\Http\Controllers\Catalogos\MetodoPagoController;
use App\Http\Controllers\Catalogos\PresentacionController;
use App\Http\Controllers\Catalogos\ProductoController;
use App\Http\Controllers\Catalogos\ProveedorController;
use App\Http\Controllers\Catalogos\TipoComprobanteController;
use App\Http\Controllers\Catalogos\TipoDocumentoController;
use App\Http\Controllers\Catalogos\UnidadMedidaController;
use Illuminate\Support\Facades\Route;

/* Catálogos administrables y sus permisos específicos. */
Route::get('/productos', [ProductoController::class, 'index'])->middleware('permission:productos.ver')->name('productos.index');
Route::post('/productos', [ProductoController::class, 'store'])->middleware('permission:productos.gestionar')->name('productos.store');
Route::put('/productos/{producto}', [ProductoController::class, 'update'])->middleware('permission:productos.gestionar')->name('productos.update');
Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('permission:productos.gestionar')->name('productos.destroy');
Route::post('/productos/{producto}/presentaciones', [ProductoController::class, 'storePresentacion'])->middleware('permission:productos.gestionar')->name('productos.presentaciones.store');
Route::delete('/productos/presentaciones/{productoPresentacion}', [ProductoController::class, 'destroyPresentacion'])->middleware('permission:productos.gestionar')->name('productos.presentaciones.destroy');

Route::get('/categorias', [CategoriaController::class, 'index'])->middleware('permission:productos.ver')->name('categorias.index');
Route::post('/categorias', [CategoriaController::class, 'store'])->middleware('permission:productos.gestionar')->name('categorias.store');
Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->middleware('permission:productos.gestionar')->name('categorias.update');
Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->middleware('permission:productos.gestionar')->name('categorias.destroy');

Route::get('/marcas', [MarcaController::class, 'index'])->middleware('permission:productos.ver')->name('marcas.index');
Route::post('/marcas', [MarcaController::class, 'store'])->middleware('permission:productos.gestionar')->name('marcas.store');
Route::put('/marcas/{marca}', [MarcaController::class, 'update'])->middleware('permission:productos.gestionar')->name('marcas.update');
Route::delete('/marcas/{marca}', [MarcaController::class, 'destroy'])->middleware('permission:productos.gestionar')->name('marcas.destroy');

Route::get('/unidades', [UnidadMedidaController::class, 'index'])->middleware('permission:productos.ver')->name('unidades.index');
Route::post('/unidades', [UnidadMedidaController::class, 'store'])->middleware('permission:productos.gestionar')->name('unidades.store');
Route::put('/unidades/{unidad}', [UnidadMedidaController::class, 'update'])->middleware('permission:productos.gestionar')->name('unidades.update');
Route::delete('/unidades/{unidad}', [UnidadMedidaController::class, 'destroy'])->middleware('permission:productos.gestionar')->name('unidades.destroy');

Route::get('/presentaciones', [PresentacionController::class, 'index'])->middleware('permission:productos.ver')->name('presentaciones.index');
Route::post('/presentaciones', [PresentacionController::class, 'store'])->middleware('permission:productos.gestionar')->name('presentaciones.store');
Route::put('/presentaciones/{presentacion}', [PresentacionController::class, 'update'])->middleware('permission:productos.gestionar')->name('presentaciones.update');
Route::delete('/presentaciones/{presentacion}', [PresentacionController::class, 'destroy'])->middleware('permission:productos.gestionar')->name('presentaciones.destroy');

Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permission:clientes.ver')->name('clientes.index');
Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permission:clientes.gestionar')->name('clientes.store');
Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permission:clientes.gestionar')->name('clientes.update');
Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('permission:clientes.gestionar')->name('clientes.destroy');

Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permission:proveedores.ver')->name('proveedores.index');
Route::post('/proveedores', [ProveedorController::class, 'store'])->middleware('permission:proveedores.gestionar')->name('proveedores.store');
Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permission:proveedores.gestionar')->name('proveedores.update');
Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->middleware('permission:proveedores.gestionar')->name('proveedores.destroy');

Route::get('/metodos-pago', [MetodoPagoController::class, 'index'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.index');
Route::post('/metodos-pago', [MetodoPagoController::class, 'store'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.store');
Route::put('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'update'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.update');
Route::delete('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'destroy'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.destroy');

Route::get('/tipos-comprobante', [TipoComprobanteController::class, 'index'])->middleware('permission:tipos_comprobante.ver')->name('tipos-comprobante.index');
Route::post('/tipos-comprobante', [TipoComprobanteController::class, 'store'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.store');
Route::put('/tipos-comprobante/{tipoComprobante}', [TipoComprobanteController::class, 'update'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.update');
Route::delete('/tipos-comprobante/{tipoComprobante}', [TipoComprobanteController::class, 'destroy'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.destroy');

Route::get('/tipos-documento', [TipoDocumentoController::class, 'index'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.index');
Route::post('/tipos-documento', [TipoDocumentoController::class, 'store'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.store');
Route::put('/tipos-documento/{tipoDocumento}', [TipoDocumentoController::class, 'update'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.update');
Route::delete('/tipos-documento/{tipoDocumento}', [TipoDocumentoController::class, 'destroy'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.destroy');
