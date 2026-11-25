<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalogos\CategoriaController;
use App\Http\Controllers\Catalogos\ImpresoraTerminalController;
use App\Http\Controllers\Catalogos\ClienteController;
use App\Http\Controllers\Configuracion\DatosGeneralesController;
use App\Http\Controllers\Dashboard\DashboardPageController;
use App\Http\Controllers\Integraciones\DocumentLookupController;
use App\Http\Controllers\Integraciones\IAController;
use App\Http\Controllers\Catalogos\MarcaController;
use App\Http\Controllers\Catalogos\MetodoPagoController;
use App\Http\Controllers\Catalogos\PresentacionController;
use App\Http\Controllers\Catalogos\ProductoController;
use App\Http\Controllers\Catalogos\ProveedorController;
use App\Http\Controllers\Catalogos\TipoComprobanteController;
use App\Http\Controllers\Catalogos\TipoDocumentoController;
use App\Http\Controllers\Catalogos\UnidadMedidaController;
use App\Http\Controllers\Inventario\KardexController;
use App\Http\Controllers\Inventario\SucursalActivaController;
use App\Http\Controllers\Inventario\SucursalController;
use App\Http\Controllers\ModulePageController;
use App\Http\Controllers\Operaciones\IngresoController;
use App\Http\Controllers\Operaciones\ComprobanteArchivoController;
use App\Http\Controllers\Operaciones\TurnoCajaController;
use App\Http\Controllers\Operaciones\TicketTermicoController;
use App\Http\Controllers\Operaciones\VentaController;
use App\Http\Controllers\Seguridad\RolController;
use App\Http\Controllers\Seguridad\UsuarioController;
use App\Http\Controllers\Seguridad\UsuarioPageController;
use App\Http\Controllers\Operaciones\SunatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ESTADO DEL SERVICIO
|--------------------------------------------------------------------------
| Ruta pública, sin dependencias de autenticación ni consultas a PostgreSQL.
| La usa el despliegue para confirmar que Laravel responde tras publicar.
*/
Route::get('/salud', fn () => response()->json(['estado' => 'ok']))->name('salud');

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (AUTENTICACIÓN)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS DEL SISTEMA
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {

    // --- 1. DASHBOARD PRINCIPAL ---
    Route::get('/', DashboardPageController::class)->middleware('permission:dashboard.ver')->name('dashboard');
    Route::get('/dashboard', DashboardPageController::class)->middleware('permission:dashboard.ver');

    // --- 2. CONFIGURACIÓN Y DATOS GENERALES ---
    Route::get('/datos-generales', [DatosGeneralesController::class, 'index'])->middleware('permission:dashboard.ver')->name('datos-generales.index');
    Route::put('/datos-generales', [DatosGeneralesController::class, 'update'])->middleware('permission:dashboard.ver')->name('datos-generales.update');
    Route::get('/configuracion', [DatosGeneralesController::class, 'index'])->middleware('permission:dashboard.ver')->name('configuracion.index');

    // --- 3. MÓDULO DE VENTAS Y CAJA (POS) ---
    Route::get('/caja', [TurnoCajaController::class, 'index'])->middleware('permission:ventas.registrar')->name('caja.index');
    Route::post('/caja/aperturar', [TurnoCajaController::class, 'apertura'])->middleware('permission:ventas.registrar')->name('caja.apertura');
    Route::post('/caja/{idTurno}/terminal', [TurnoCajaController::class, 'asignarTerminal'])->middleware('permission:ventas.registrar')->name('caja.terminal.asignar');
    Route::post('/caja/movimientos', [TurnoCajaController::class, 'registrarMovimiento'])->middleware('permission:ventas.registrar')->name('caja.movimientos.store');
    Route::get('/caja/{idTurno}/resumen', [TurnoCajaController::class, 'resumenArqueo'])->middleware('permission:ventas.registrar')->name('caja.resumen');
    Route::post('/caja/{idTurno}/cerrar', [TurnoCajaController::class, 'cierre'])->middleware('permission:ventas.registrar')->name('caja.cierre');
    Route::get('/ventas', [VentaController::class, 'index'])->middleware('permission:ventas.registrar')->name('ventas.index');
    Route::get('/ventas/clientes/buscar', [VentaController::class, 'buscarClientes'])->middleware(['permission:ventas.registrar', 'throttle:60,1'])->name('ventas.clientes.buscar');
    Route::get('/ventas/{venta}/archivo/{tipo}', [ComprobanteArchivoController::class, 'download'])
        ->whereIn('tipo', ['xml', 'cdr', 'pdf'])
        ->middleware('permission:ventas.registrar')
        ->name('ventas.archivos.download');
    // Restaurante: se vende todo desde un solo POS (/pos). Se conserva el nombre de la ruta
    // porque caja y otras pantallas redirigen a ventas.create.
    Route::redirect('/ventas/create', '/pos')->middleware('permission:ventas.registrar')->name('ventas.create');
    Route::get('/ventas/productos/buscar', [VentaController::class, 'buscarProductos'])->middleware(['permission:ventas.registrar', 'throttle:60,1'])->name('ventas.productos.buscar');
    Route::post('/ventas', [VentaController::class, 'store'])->middleware('permission:ventas.registrar')->name('ventas.store');
    Route::get('/ventas/{venta}/representacion', [TicketTermicoController::class, 'representacion'])->middleware('permission:ventas.registrar')->name('ventas.representacion');
    Route::post('/ventas/{venta}/imprimir-ticket', [TicketTermicoController::class, 'imprimir'])->middleware('permission:ventas.registrar')->name('ventas.ticket.imprimir');
    Route::post('/ventas/buscar-cliente', [VentaController::class, 'buscarYGuardarCliente'])->middleware('permission:ventas.registrar')->name('ventas.buscar-cliente');

    // --- 4. ALMACÉN: INGRESOS (COMPRAS) ---
    Route::get('/ingresos', [IngresoController::class, 'index'])->middleware('permission:ingresos.registrar')->name('ingresos.index');
    Route::get('/ingresos/create', [IngresoController::class, 'create'])->middleware('permission:ingresos.registrar')->name('ingresos.create');
    Route::post('/ingresos', [IngresoController::class, 'store'])->middleware('permission:ingresos.registrar')->name('ingresos.store');

    // --- 5. ALMACÉN: SALIDAS (MERMAS) ---
    // Consultar el historial también es una capacidad explícita del rol.

    // --- 6. INVENTARIO Y KARDEX VALORIZADO ---
    Route::redirect('/inventario', '/kardex')->middleware('permission:inventario.ver')->name('inventario.index');
    Route::get('/kardex', [KardexController::class, 'index'])->middleware('permission:kardex.ver')->name('kardex.index');
    // Ajustar existencias o precios modifica información financiera; nunca basta con ver Kardex.
    Route::post('/kardex/ajustar', [KardexController::class, 'ajustarStockYPrecio'])->middleware('permission:inventario.gestionar')->name('kardex.ajustar');

    // --- 7. CATÁLOGOS: PRODUCTOS, PRESENTACIONES Y MARCAS ---
    Route::get('/productos', [ProductoController::class, 'index'])->middleware('permission:productos.ver')->name('productos.index');
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->middleware('permission:productos.ver')->name('productos.show');
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

    // --- 8. CATÁLOGOS: CLIENTES Y PROVEEDORES ---
    Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permission:clientes.ver')->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permission:clientes.gestionar')->name('clientes.store');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permission:clientes.gestionar')->name('clientes.update');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('permission:clientes.gestionar')->name('clientes.destroy');

    Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permission:proveedores.ver')->name('proveedores.index');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->middleware('permission:proveedores.gestionar')->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permission:proveedores.gestionar')->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->middleware('permission:proveedores.gestionar')->name('proveedores.destroy');

    // --- 9. PARÁMETROS Y MÉTODOS DE PAGO SUNAT ---
    Route::get('/metodos-pago', [MetodoPagoController::class, 'index'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.index');
    Route::post('/metodos-pago', [MetodoPagoController::class, 'store'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.store');
    Route::put('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'update'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.update');
    Route::delete('/metodos-pago/{metodoPago}', [MetodoPagoController::class, 'destroy'])->middleware('permission:metodos_pago.gestionar')->name('metodos-pago.destroy');

    // El inventario de impresoras y terminales es configuración administrativa por sucursal.
    Route::get('/impresoras', [ImpresoraTerminalController::class, 'index'])->middleware('permission:usuarios.gestionar')->name('impresoras.index');
    Route::post('/impresoras', [ImpresoraTerminalController::class, 'storeImpresora'])->middleware('permission:usuarios.gestionar')->name('impresoras.store');
    Route::put('/impresoras/{impresora}', [ImpresoraTerminalController::class, 'updateImpresora'])->middleware('permission:usuarios.gestionar')->name('impresoras.update');
    Route::post('/impresoras/{impresora}/diagnosticar', [ImpresoraTerminalController::class, 'diagnosticar'])->middleware('permission:usuarios.gestionar')->name('impresoras.diagnosticar');
    Route::post('/impresoras/{impresora}/prueba', [ImpresoraTerminalController::class, 'prueba'])->middleware('permission:usuarios.gestionar')->name('impresoras.prueba');
    Route::post('/terminales-pos', [ImpresoraTerminalController::class, 'storeTerminal'])->middleware('permission:usuarios.gestionar')->name('terminales-pos.store');
    Route::put('/terminales-pos/{terminalPos}', [ImpresoraTerminalController::class, 'updateTerminal'])->middleware('permission:usuarios.gestionar')->name('terminales-pos.update');
    Route::post('/terminales-pos/{terminalPos}/vinculacion', [ImpresoraTerminalController::class, 'crearCodigoVinculacion'])->middleware('permission:usuarios.gestionar')->name('terminales-pos.vinculacion.crear');
    Route::delete('/terminales-pos/{terminalPos}/vinculacion', [ImpresoraTerminalController::class, 'revocarAgente'])->middleware('permission:usuarios.gestionar')->name('terminales-pos.vinculacion.revocar');

    // --- SUCURSALES (MULTISUCURSAL) ---
    // La gestión exige además rol administrador (controlador y Form Request).
    Route::get('/sucursales', [SucursalController::class, 'index'])->middleware('permission:usuarios.gestionar')->name('sucursales.index');
    Route::post('/sucursales', [SucursalController::class, 'store'])->middleware('permission:usuarios.gestionar')->name('sucursales.store');
    Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->middleware('permission:usuarios.gestionar')->name('sucursales.update');
    Route::delete('/sucursales/{sucursal}', [SucursalController::class, 'destroy'])->middleware('permission:usuarios.gestionar')->name('sucursales.destroy');
    // Cambiar la sucursal de trabajo solo afecta a la sesión; el acceso se valida en el servidor.
    Route::post('/sucursal-activa', [SucursalActivaController::class, 'update'])->middleware('throttle:30,1')->name('sucursal-activa.update');

    Route::get('/tipos-comprobante', [TipoComprobanteController::class, 'index'])->middleware('permission:tipos_comprobante.ver')->name('tipos-comprobante.index');
    Route::post('/tipos-comprobante', [TipoComprobanteController::class, 'store'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.store');
    Route::put('/tipos-comprobante/{tipoComprobante}', [TipoComprobanteController::class, 'update'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.update');
    Route::delete('/tipos-comprobante/{tipoComprobante}', [TipoComprobanteController::class, 'destroy'])->middleware('permission:tipos_comprobante.gestionar')->name('tipos-comprobante.destroy');

    Route::get('/tipos-documento', [TipoDocumentoController::class, 'index'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.index');
    Route::post('/tipos-documento', [TipoDocumentoController::class, 'store'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.store');
    Route::put('/tipos-documento/{tipoDocumento}', [TipoDocumentoController::class, 'update'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.update');
    Route::delete('/tipos-documento/{tipoDocumento}', [TipoDocumentoController::class, 'destroy'])->middleware('permission:tipos_documento.gestionar')->name('tipos-documento.destroy');

    // --- 10. SEGURIDAD: USUARIOS Y ROLES ---
    Route::get('/usuarios', UsuarioPageController::class)->middleware('permission:usuarios.ver')->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('permission:usuarios.gestionar')->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permission:usuarios.gestionar')->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->middleware('permission:usuarios.gestionar')->name('usuarios.destroy');

    Route::get('/roles', [RolController::class, 'index'])->middleware('permission:usuarios.ver')->name('roles.index');
    Route::post('/roles', [RolController::class, 'store'])->middleware('permission:usuarios.gestionar')->name('roles.store');
    Route::put('/roles/{rol}', [RolController::class, 'update'])->middleware('permission:usuarios.gestionar')->name('roles.update');
    Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->middleware('permission:usuarios.gestionar')->name('roles.destroy');
    Route::put('/roles/{rol}/permisos', [UsuarioController::class, 'updatePermissions'])->middleware('permission:usuarios.gestionar')->name('roles.permissions.update');

    // --- 11. MÓDULOS AUXILIARES ---
    Route::redirect('/reportes', '/reportes/restaurante')->middleware('permission:reportes.restaurante')->name('modules.reportes');

    // --- 12. INTELIGENCIA ARTIFICIAL & SERVICIOS EXTERNOS ---
    Route::post('/ia/preguntar', [IAController::class, 'preguntar'])->middleware(['permission:dashboard.ver', 'throttle:12,1'])->name('ia.preguntar');
    Route::post('/ia/chat', [IAController::class, 'chat'])->middleware(['permission:dashboard.ver', 'throttle:12,1'])->name('ia.chat');
    Route::post('/api/documentos/consulta', DocumentLookupController::class)->middleware('throttle:20,1')->name('documentos.lookup');
    Route::post('/emitir', [SunatController::class, 'emitir'])
        ->middleware('permission:ventas.registrar')
        ->name('sunat.emitir');

    // --- 12.1 RESTAURANTE: salón, pedidos y carta ---
    require __DIR__.'/restaurante.php';

    // --- 13. CERRAR SESIÓN ---
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
