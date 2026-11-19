<?php

namespace Tests\Feature\Restaurante;

use Illuminate\Support\Facades\DB;

/**
 * Réplica mínima en SQLite de las tablas que usan los servicios del restaurante.
 * Reproduce las garantías que importan para las pruebas: claves de idempotencia
 * únicas y una sola cuenta ABIERTA por mesa (índice único parcial).
 *
 * Sucursal 1: área "Salón" con mesas M1 (id 1) y M2 (id 2).
 * Sucursal 2: área "Terraza" con mesa T1 (id 3).
 * Carta: Lomo saltado (precio 1, S/ 30) con grupo obligatorio "Término" y opcional
 * "Adicionales"; Gaseosa (precio 2, S/ 5); Papa (precio 3, insumo).
 */
trait PreparaBaseRestaurante
{
    protected function prepararBaseRestaurante(): void
    {
        foreach (['inventario', 'catalogos', 'restaurante'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }

        DB::statement('CREATE TABLE inventario.sucursales (id_sucursal INTEGER PRIMARY KEY, nombre TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement("CREATE TABLE catalogos.productos (id_producto INTEGER PRIMARY KEY, nombre TEXT, estado BOOLEAN DEFAULT 1, tipo_producto TEXT DEFAULT 'DIRECTO')");
        DB::statement('CREATE TABLE inventario.precio_producto (id_precio INTEGER PRIMARY KEY, id_producto INTEGER, id_presentacion INTEGER, factor_conversion NUMERIC DEFAULT 1, precio_venta NUMERIC, porcentaje_utilidad NUMERIC NULL, estado BOOLEAN DEFAULT 1)');

        DB::statement('CREATE TABLE restaurante.areas (id_area INTEGER PRIMARY KEY, id_sucursal INTEGER, nombre TEXT, orden INTEGER DEFAULT 0, activo BOOLEAN DEFAULT 1, created_at DATETIME, updated_at DATETIME)');
        DB::statement("CREATE TABLE restaurante.mesas (id_mesa INTEGER PRIMARY KEY, id_sucursal INTEGER, id_area INTEGER, codigo TEXT, capacidad INTEGER DEFAULT 4, estado TEXT DEFAULT 'DISPONIBLE', orden INTEGER DEFAULT 0, activo BOOLEAN DEFAULT 1, created_at DATETIME, updated_at DATETIME)");
        DB::statement('CREATE TABLE restaurante.grupos_modificadores (id_grupo INTEGER PRIMARY KEY, nombre TEXT, seleccion_minima INTEGER DEFAULT 0, seleccion_maxima INTEGER DEFAULT 1, activo BOOLEAN DEFAULT 1, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE restaurante.opciones_modificador (id_opcion INTEGER PRIMARY KEY, id_grupo INTEGER, nombre TEXT, precio_adicional NUMERIC DEFAULT 0, orden INTEGER DEFAULT 0, activo BOOLEAN DEFAULT 1, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE restaurante.producto_grupo_modificador (id_producto INTEGER, id_grupo INTEGER, orden INTEGER DEFAULT 0, PRIMARY KEY (id_producto, id_grupo))');
        DB::statement('CREATE TABLE restaurante.productos_agotados (id_sucursal INTEGER, id_producto INTEGER, id_usuario INTEGER, marcado_en DATETIME, PRIMARY KEY (id_sucursal, id_producto))');
        DB::statement("CREATE TABLE restaurante.pedidos (id_pedido INTEGER PRIMARY KEY, id_sucursal INTEGER, numero INTEGER, tipo TEXT, id_mesa INTEGER NULL, id_usuario INTEGER, id_cliente INTEGER NULL, comensales INTEGER NULL, nombre_contacto TEXT NULL, telefono_contacto TEXT NULL, direccion_entrega TEXT NULL, observacion TEXT NULL, estado TEXT DEFAULT 'ABIERTO', clave_idempotencia TEXT UNIQUE, abierto_en DATETIME, cerrado_en DATETIME NULL, id_usuario_cierre INTEGER NULL, motivo_anulacion TEXT NULL, id_pedido_destino INTEGER NULL, created_at DATETIME, updated_at DATETIME, UNIQUE (id_sucursal, numero))");
        DB::statement("CREATE UNIQUE INDEX restaurante.pedidos_mesa_abierta_unique ON pedidos (id_mesa) WHERE estado = 'ABIERTO' AND id_mesa IS NOT NULL");
        DB::statement("CREATE TABLE restaurante.pedido_detalles (id_detalle_pedido INTEGER PRIMARY KEY, id_pedido INTEGER, id_precio INTEGER, id_producto INTEGER, nombre_producto TEXT, cantidad NUMERIC, precio_base NUMERIC, precio_modificadores NUMERIC DEFAULT 0, precio_unitario NUMERIC, subtotal NUMERIC, observacion TEXT NULL, estado TEXT DEFAULT 'PENDIENTE', cantidad_cobrada NUMERIC DEFAULT 0, id_usuario INTEGER, id_comanda INTEGER NULL, enviado_en DATETIME NULL, motivo_anulacion TEXT NULL, id_usuario_anulacion INTEGER NULL, anulado_en DATETIME NULL, clave_idempotencia TEXT UNIQUE, created_at DATETIME, updated_at DATETIME)");
        DB::statement('CREATE TABLE restaurante.pedido_detalle_modificadores (id_detalle_modificador INTEGER PRIMARY KEY, id_detalle_pedido INTEGER, id_opcion INTEGER, nombre_grupo TEXT, nombre_opcion TEXT, precio_adicional NUMERIC DEFAULT 0)');
        DB::statement('CREATE TABLE restaurante.pedido_eventos (id_evento INTEGER PRIMARY KEY, id_pedido INTEGER, id_detalle_pedido INTEGER NULL, id_usuario INTEGER, tipo TEXT, datos TEXT NULL, creado_en DATETIME)');

        DB::table('inventario.sucursales')->insert([['id_sucursal' => 1, 'nombre' => 'Centro'], ['id_sucursal' => 2, 'nombre' => 'Norte']]);
        DB::table('restaurante.areas')->insert([
            ['id_area' => 1, 'id_sucursal' => 1, 'nombre' => 'Salón'],
            ['id_area' => 2, 'id_sucursal' => 2, 'nombre' => 'Terraza'],
        ]);
        DB::table('restaurante.mesas')->insert([
            ['id_mesa' => 1, 'id_sucursal' => 1, 'id_area' => 1, 'codigo' => 'M1'],
            ['id_mesa' => 2, 'id_sucursal' => 1, 'id_area' => 1, 'codigo' => 'M2'],
            ['id_mesa' => 3, 'id_sucursal' => 2, 'id_area' => 2, 'codigo' => 'T1'],
        ]);

        DB::table('catalogos.productos')->insert([
            ['id_producto' => 1, 'nombre' => 'Lomo saltado', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 2, 'nombre' => 'Gaseosa', 'tipo_producto' => 'DIRECTO'],
            ['id_producto' => 3, 'nombre' => 'Papa', 'tipo_producto' => 'INSUMO'],
        ]);
        DB::table('inventario.precio_producto')->insert([
            ['id_precio' => 1, 'id_producto' => 1, 'id_presentacion' => 1, 'precio_venta' => 30],
            ['id_precio' => 2, 'id_producto' => 2, 'id_presentacion' => 1, 'precio_venta' => 5],
            ['id_precio' => 3, 'id_producto' => 3, 'id_presentacion' => 1, 'precio_venta' => 2],
        ]);

        DB::table('restaurante.grupos_modificadores')->insert([
            ['id_grupo' => 1, 'nombre' => 'Término', 'seleccion_minima' => 1, 'seleccion_maxima' => 1],
            ['id_grupo' => 2, 'nombre' => 'Adicionales', 'seleccion_minima' => 0, 'seleccion_maxima' => 2],
            ['id_grupo' => 3, 'nombre' => 'Hielo', 'seleccion_minima' => 0, 'seleccion_maxima' => 1],
        ]);
        DB::table('restaurante.opciones_modificador')->insert([
            ['id_opcion' => 1, 'id_grupo' => 1, 'nombre' => 'Jugoso', 'precio_adicional' => 0],
            ['id_opcion' => 2, 'id_grupo' => 1, 'nombre' => 'Bien cocido', 'precio_adicional' => 0],
            ['id_opcion' => 3, 'id_grupo' => 2, 'nombre' => 'Huevo frito', 'precio_adicional' => 2.5],
            ['id_opcion' => 4, 'id_grupo' => 2, 'nombre' => 'Plátano', 'precio_adicional' => 3],
            ['id_opcion' => 5, 'id_grupo' => 3, 'nombre' => 'Sin hielo', 'precio_adicional' => 0],
        ]);
        DB::table('restaurante.producto_grupo_modificador')->insert([
            ['id_producto' => 1, 'id_grupo' => 1, 'orden' => 0],
            ['id_producto' => 1, 'id_grupo' => 2, 'orden' => 1],
            ['id_producto' => 2, 'id_grupo' => 3, 'orden' => 0],
        ]);
    }

    /** Ítem de lomo saltado jugoso, con la clave indicada. */
    protected function lomo(string $clave, float $cantidad = 1, array $opciones = [1]): array
    {
        return ['clave' => $clave, 'id_precio' => 1, 'cantidad' => $cantidad, 'opciones' => $opciones];
    }

    protected function gaseosa(string $clave, float $cantidad = 1): array
    {
        return ['clave' => $clave, 'id_precio' => 2, 'cantidad' => $cantidad];
    }

    protected function estadoMesa(int $mesaId): string
    {
        return DB::table('restaurante.mesas')->where('id_mesa', $mesaId)->value('estado');
    }
}
