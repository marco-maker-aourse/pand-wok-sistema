<?php

use App\Http\Controllers\Restaurante\CartaController;
use App\Http\Controllers\Restaurante\CocinaController;
use App\Http\Controllers\Restaurante\CobroPedidoController;
use App\Http\Controllers\Restaurante\EstacionController;
use App\Http\Controllers\Restaurante\PedidoController;
use App\Http\Controllers\Restaurante\PosController;
use App\Http\Controllers\Restaurante\RecetaController;
use App\Http\Controllers\Restaurante\ReporteRestauranteController;
use App\Http\Controllers\Restaurante\SalonController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RESTAURANTE (se carga dentro del grupo `auth` de routes/web.php)
|--------------------------------------------------------------------------
| Los ids viajan como enteros; cada servicio valida la sucursal activa.
*/

// Punto de venta único (mostrador, para llevar, delivery y mesas)
Route::get('/pos', [PosController::class, 'index'])->middleware('permission:pedidos.registrar')->name('restaurante.pos');
Route::post('/pos/enviar', [PosController::class, 'enviar'])->middleware(['permission:pedidos.registrar', 'permission:comandas.enviar', 'throttle:60,1'])->name('restaurante.pos.enviar');
Route::post('/pos/clientes', [PosController::class, 'cliente'])->middleware(['permission:ventas.registrar', 'throttle:30,1'])->name('restaurante.pos.cliente');
Route::post('/pos/cobrar', [PosController::class, 'cobrar'])->middleware(['permission:pedidos.registrar', 'permission:ventas.registrar', 'throttle:60,1'])->name('restaurante.pos.cobrar');

// Cocina
Route::get('/cocina', [CocinaController::class, 'index'])->middleware('permission:cocina.ver')->name('restaurante.cocina');
Route::put('/cocina/comandas/{comanda}/estado', [CocinaController::class, 'estado'])->whereNumber('comanda')->middleware('permission:cocina.gestionar')->name('restaurante.comandas.estado');
Route::post('/cocina/comandas/{comanda}/reimprimir', [CocinaController::class, 'reimprimir'])->whereNumber('comanda')->middleware('throttle:30,1')->name('restaurante.comandas.reimprimir');
Route::get('/cocina/estaciones', [EstacionController::class, 'index'])->middleware('permission:cocina.configurar')->name('restaurante.estaciones');
Route::post('/cocina/estaciones', [EstacionController::class, 'guardar'])->middleware('permission:cocina.configurar')->name('restaurante.estaciones.store');
Route::put('/cocina/estaciones/{estacion}', [EstacionController::class, 'guardar'])->whereNumber('estacion')->middleware('permission:cocina.configurar')->name('restaurante.estaciones.update');
Route::post('/pedidos/{pedido}/enviar-cocina', [PedidoController::class, 'enviar'])->whereNumber('pedido')->middleware(['permission:pedidos.registrar', 'permission:comandas.enviar', 'throttle:60,1'])->name('restaurante.pedidos.enviar');
Route::post('/pedidos/{pedido}/guardar-y-enviar', [PedidoController::class, 'guardarYEnviar'])->whereNumber('pedido')->middleware(['permission:pedidos.registrar', 'permission:comandas.enviar', 'throttle:60,1'])->name('restaurante.pedidos.guardar-y-enviar');

// Caja de pedidos
Route::get('/pedidos/{pedido}/cobrar', [CobroPedidoController::class, 'show'])->whereNumber('pedido')->middleware('permission:ventas.registrar')->name('restaurante.cobrar.show');
Route::post('/pedidos/{pedido}/cobrar', [CobroPedidoController::class, 'store'])->whereNumber('pedido')->middleware(['permission:ventas.registrar', 'throttle:30,1'])->name('restaurante.cobrar.store');

// Inventario de cocina
Route::get('/recetas', [RecetaController::class, 'index'])->middleware('permission:recetas.gestionar')->name('restaurante.recetas');
Route::put('/recetas/{precio}', [RecetaController::class, 'guardar'])->whereNumber('precio')->middleware('permission:recetas.gestionar')->name('restaurante.recetas.guardar');
Route::get('/mermas', [RecetaController::class, 'index'])->middleware('permission:mermas.registrar')->name('restaurante.mermas');
Route::post('/mermas', [RecetaController::class, 'merma'])->middleware(['permission:mermas.registrar', 'throttle:30,1'])->name('restaurante.mermas.store');

Route::get('/reportes/restaurante', ReporteRestauranteController::class)->middleware('permission:reportes.restaurante')->name('restaurante.reportes');

// Salón
Route::get('/salon', [SalonController::class, 'index'])->middleware('permission:mesas.ver')->name('restaurante.salon');
Route::get('/salon/configuracion', [SalonController::class, 'configuracion'])->middleware('permission:mesas.gestionar')->name('restaurante.salon.configuracion');
Route::post('/salon/areas', [SalonController::class, 'guardarArea'])->middleware('permission:mesas.gestionar')->name('restaurante.areas.store');
Route::put('/salon/areas/{area}', [SalonController::class, 'guardarArea'])->whereNumber('area')->middleware('permission:mesas.gestionar')->name('restaurante.areas.update');
Route::post('/salon/mesas', [SalonController::class, 'guardarMesa'])->middleware('permission:mesas.gestionar')->name('restaurante.mesas.store');
Route::put('/salon/mesas/{mesa}', [SalonController::class, 'guardarMesa'])->whereNumber('mesa')->middleware('permission:mesas.gestionar')->name('restaurante.mesas.update');
Route::put('/salon/mesas/{mesa}/estado', [SalonController::class, 'cambiarEstadoMesa'])->whereNumber('mesa')->middleware('permission:pedidos.registrar')->name('restaurante.mesas.estado');

// Pedidos (cuentas)
Route::post('/pedidos', [PedidoController::class, 'store'])->middleware(['permission:pedidos.registrar', 'throttle:60,1'])->name('restaurante.pedidos.store');
Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->whereNumber('pedido')->middleware('permission:pedidos.registrar')->name('restaurante.pedidos.show');
Route::post('/pedidos/{pedido}/items', [PedidoController::class, 'agregarItems'])->whereNumber('pedido')->middleware(['permission:pedidos.registrar', 'throttle:120,1'])->name('restaurante.pedidos.items.store');
Route::post('/pedidos/{pedido}/anular', [PedidoController::class, 'anular'])->whereNumber('pedido')->middleware('permission:pedidos.registrar')->name('restaurante.pedidos.anular');
Route::post('/pedidos/{pedido}/transferir', [PedidoController::class, 'transferir'])->whereNumber('pedido')->middleware('permission:pedidos.transferir')->name('restaurante.pedidos.transferir');
Route::put('/pedidos/items/{detalle}', [PedidoController::class, 'actualizarItem'])->whereNumber('detalle')->middleware('permission:pedidos.registrar')->name('restaurante.pedidos.items.update');
Route::post('/pedidos/items/{detalle}/anular', [PedidoController::class, 'anularItem'])->whereNumber('detalle')->middleware('permission:pedidos.registrar')->name('restaurante.pedidos.items.anular');

// Carta y modificadores
Route::get('/carta', [CartaController::class, 'index'])->middleware('permission:carta.gestionar')->name('restaurante.carta');
Route::put('/carta/productos/{producto}/tipo', [CartaController::class, 'tipo'])->whereNumber('producto')->middleware('permission:carta.gestionar')->name('restaurante.carta.tipo');
Route::put('/carta/productos/{producto}/agotado', [CartaController::class, 'agotado'])->whereNumber('producto')->middleware('permission:carta.gestionar')->name('restaurante.carta.agotado');
Route::put('/carta/precios/{precio}', [CartaController::class, 'precioSucursal'])->whereNumber('precio')->middleware('permission:carta.gestionar')->name('restaurante.carta.precio');
Route::put('/carta/productos/{producto}/modificadores', [CartaController::class, 'grupos'])->whereNumber('producto')->middleware('permission:carta.gestionar')->name('restaurante.carta.grupos');
Route::post('/carta/modificadores', [CartaController::class, 'guardarGrupo'])->middleware('permission:carta.gestionar')->name('restaurante.modificadores.store');
Route::put('/carta/modificadores/{grupo}', [CartaController::class, 'guardarGrupo'])->whereNumber('grupo')->middleware('permission:carta.gestionar')->name('restaurante.modificadores.update');
Route::post('/carta/modificadores/{grupo}/opciones', [CartaController::class, 'guardarOpcion'])->whereNumber('grupo')->middleware('permission:carta.gestionar')->name('restaurante.opciones.store');
Route::put('/carta/opciones/{opcion}', [CartaController::class, 'guardarOpcion'])->whereNumber('opcion')->middleware('permission:carta.gestionar')->name('restaurante.opciones.update');
