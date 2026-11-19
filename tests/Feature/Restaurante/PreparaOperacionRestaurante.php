<?php

namespace Tests\Feature\Restaurante;

use App\Models\Kardex;
use Illuminate\Support\Facades\DB;

/**
 * Base de operación completa: la del restaurante más seguridad, caja, comprobantes,
 * pagos, ventas, Kardex y lotes. Usuarios: 10 Administrador General, 20 Cajero, 30 Mozo.
 * Cliente 1 = público general (00000000); cliente 2 con RUC.
 */
trait PreparaOperacionRestaurante
{
    use PreparaBaseRestaurante;

    protected function prepararOperacionRestaurante(): void
    {
        $this->prepararBaseRestaurante();
        Kardex::olvidarEsquema();
        DB::statement("ATTACH DATABASE ':memory:' AS seguridad");
        DB::statement('CREATE TABLE seguridad.roles (id_rol INTEGER PRIMARY KEY, nombre TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, id_rol INTEGER, id_sucursal INTEGER, nombres TEXT, email TEXT, password TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.permisos (id_permiso INTEGER PRIMARY KEY, codigo TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.rol_permisos (id_rol INTEGER, id_permiso INTEGER)');
        DB::statement('CREATE TABLE seguridad.usuario_sucursales (id_usuario INTEGER, id_sucursal INTEGER)');
        DB::statement('ALTER TABLE inventario.sucursales ADD COLUMN codigo_establecimiento TEXT');
        DB::statement('CREATE TABLE catalogos.clientes (id_cliente INTEGER PRIMARY KEY, id_tipo_documento INTEGER, nombre_razon_social TEXT, num_documento TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE catalogos.tipos_documento (id_tipo_documento INTEGER PRIMARY KEY, codigo_sunat TEXT)');
        DB::statement('CREATE TABLE catalogos.tipos_comprobante (id_tipo_comprobante INTEGER PRIMARY KEY, codigo_sunat TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE catalogos.metodos_pago (id_metodo INTEGER PRIMARY KEY, nombre TEXT, tipo TEXT, requiere_referencia BOOLEAN DEFAULT 0, comision_porcentaje NUMERIC DEFAULT 0, comision_fija NUMERIC DEFAULT 0, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE operaciones.datos_gen (id_datos_gen INTEGER PRIMARY KEY, id_sucursal INTEGER, igv_porcentaje NUMERIC, serie_boleta TEXT, ult_nro_boleta INTEGER DEFAULT 0, serie_factura TEXT, ult_nro_factura INTEGER DEFAULT 0)');
        DB::statement('CREATE TABLE operaciones.turnos_caja (id_turno INTEGER PRIMARY KEY, id_usuario INTEGER, id_sucursal INTEGER, id_terminal INTEGER, fecha_apertura DATETIME, fecha_cierre DATETIME, estado TEXT, monto_apertura NUMERIC, monto_cierre_declarado NUMERIC)');
        DB::statement('CREATE TABLE operaciones.movimientos_caja (id_movimiento INTEGER PRIMARY KEY, id_turno INTEGER, id_usuario INTEGER, tipo_movimiento TEXT, monto NUMERIC, motivo TEXT, fecha_movimiento DATETIME)');
        DB::statement("CREATE TABLE operaciones.ventas (id_venta INTEGER PRIMARY KEY, id_sucursal INTEGER, id_cliente INTEGER, id_usuario INTEGER, id_turno INTEGER, id_tipo_comprobante INTEGER, serie TEXT, correlativo INTEGER, fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP, op_gravadas NUMERIC, op_exoneradas NUMERIC, igv NUMERIC, descuento_global NUMERIC, total_venta NUMERIC, estado_sunat TEXT DEFAULT 'PENDIENTE', id_pedido INTEGER, clave_cobro TEXT, huella_cobro TEXT, UNIQUE (id_pedido, clave_cobro))");
        DB::statement('CREATE TABLE operaciones.detalle_ventas (id_detalle INTEGER PRIMARY KEY, id_venta INTEGER, id_precio INTEGER, id_lote INTEGER, cantidad NUMERIC, precio_unitario NUMERIC, subtotal NUMERIC, id_detalle_pedido INTEGER)');
        DB::statement('CREATE TABLE operaciones.pagos_ventas (id_pago INTEGER PRIMARY KEY, id_venta INTEGER, id_metodo INTEGER, monto NUMERIC, monto_neto NUMERIC, comision_pasarela NUMERIC, nro_referencia TEXT, estado_pago TEXT, monto_recibido NUMERIC, vuelto NUMERIC)');
        DB::statement('CREATE TABLE inventario.kardex (id_kardex INTEGER PRIMARY KEY, id_producto INTEGER, id_sucursal INTEGER, cantidad_entrada NUMERIC, cantidad_salida NUMERIC, stock_actual NUMERIC, precio_costo NUMERIC, tipo_operacion TEXT, id_referencia INTEGER, documento TEXT, id_usuario INTEGER, ultima_actualizacion DATETIME)');
        DB::statement('CREATE TABLE inventario.lotes (id_lote INTEGER PRIMARY KEY, id_producto INTEGER, id_sucursal INTEGER, codigo_lote TEXT, stock NUMERIC, fecha_vencimiento DATE, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE restaurante.mermas (id_merma INTEGER PRIMARY KEY, id_sucursal INTEGER, id_usuario INTEGER, id_precio INTEGER, cantidad_base NUMERIC, costo_unitario NUMERIC, clave TEXT, huella TEXT, motivo TEXT, registrada_en DATETIME, UNIQUE (id_sucursal, clave))');

        DB::table('seguridad.roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador General'],
            ['id_rol' => 2, 'nombre' => 'Cajero'],
            ['id_rol' => 5, 'nombre' => 'Mozo'],
        ]);
        DB::table('seguridad.usuarios')->insert([
            ['id_usuario' => 10, 'id_sucursal' => 1, 'id_rol' => 1, 'nombres' => 'Ana'],
            ['id_usuario' => 20, 'id_sucursal' => 1, 'id_rol' => 2, 'nombres' => 'Leo'],
            ['id_usuario' => 30, 'id_sucursal' => 1, 'id_rol' => 5, 'nombres' => 'Luis'],
        ]);
        DB::table('seguridad.permisos')->insert([
            ['id_permiso' => 1, 'codigo' => 'ventas.registrar'], ['id_permiso' => 2, 'codigo' => 'reportes.restaurante'],
        ]);
        DB::table('seguridad.rol_permisos')->insert([['id_rol' => 2, 'id_permiso' => 1], ['id_rol' => 2, 'id_permiso' => 2]]);
        DB::table('catalogos.tipos_documento')->insert([['id_tipo_documento' => 1, 'codigo_sunat' => '0'], ['id_tipo_documento' => 2, 'codigo_sunat' => '6']]);
        DB::table('catalogos.clientes')->insert([
            ['id_cliente' => 1, 'id_tipo_documento' => 1, 'nombre_razon_social' => 'Cliente de prueba', 'num_documento' => '00000000'],
            ['id_cliente' => 2, 'id_tipo_documento' => 2, 'nombre_razon_social' => 'Empresa de prueba', 'num_documento' => '20000000001'],
        ]);
        DB::table('catalogos.tipos_comprobante')->insert([['id_tipo_comprobante' => 1, 'codigo_sunat' => '03'], ['id_tipo_comprobante' => 2, 'codigo_sunat' => '01']]);
        DB::table('catalogos.metodos_pago')->insert([
            ['id_metodo' => 1, 'nombre' => 'Efectivo', 'tipo' => 'EFECTIVO', 'requiere_referencia' => false],
            ['id_metodo' => 2, 'nombre' => 'Transferencia', 'tipo' => 'TRANSFERENCIA', 'requiere_referencia' => true],
        ]);
        DB::table('operaciones.datos_gen')->insert(['id_sucursal' => 1, 'igv_porcentaje' => 18, 'serie_boleta' => 'B001', 'serie_factura' => 'F001']);
        DB::table('inventario.kardex')->insert([
            ['id_sucursal' => 1, 'id_producto' => 2, 'stock_actual' => 20, 'precio_costo' => 2],
            ['id_sucursal' => 1, 'id_producto' => 3, 'stock_actual' => 10, 'precio_costo' => 4],
        ]);
    }
}
