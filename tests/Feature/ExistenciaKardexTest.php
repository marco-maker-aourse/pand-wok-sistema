<?php

namespace Tests\Feature;

use App\Models\Kardex;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresiones de inventario sobre SQLite en memoria.
 *
 * Los esquemas de PostgreSQL se emulan con bases adjuntas; solo se crean las
 * columnas que usan los servicios probados. Nunca toca la base de producción.
 */
class ExistenciaKardexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalogos', 'inventario', 'seguridad'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }

        DB::statement('CREATE TABLE catalogos.productos (id_producto INTEGER PRIMARY KEY, nombre TEXT, afecta_igv INTEGER DEFAULT 1, es_servicio INTEGER DEFAULT 0, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE catalogos.presentaciones (id_presentacion INTEGER PRIMARY KEY, nombre TEXT, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE catalogos.producto_presentaciones (id_precio INTEGER PRIMARY KEY, id_producto INTEGER, id_presentacion INTEGER, factor_conversion NUMERIC, precio_venta NUMERIC, estado INTEGER DEFAULT 1, id_producto_stock INTEGER NULL)');
        DB::statement('CREATE TABLE inventario.kardex (id_kardex INTEGER PRIMARY KEY, id_producto INTEGER, id_sucursal INTEGER, cantidad_entrada NUMERIC, cantidad_salida NUMERIC, stock_actual NUMERIC, precio_costo NUMERIC, tipo_operacion TEXT NULL, id_referencia INTEGER NULL, documento TEXT NULL, id_usuario INTEGER NULL, ultima_actualizacion DATETIME)');
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, nombres TEXT)');

        DB::table('catalogos.presentaciones')->insert(['id_presentacion' => 1, 'nombre' => 'UNIDAD']);
        DB::table('seguridad.usuarios')->insert(['id_usuario' => 1, 'nombres' => 'ALMACENERO']);

        // Dos productos con saldo inicial en la sucursal 1.
        foreach ([10 => 'ARROZ', 20 => 'AZUCAR'] as $id => $nombre) {
            DB::table('catalogos.productos')->insert(['id_producto' => $id, 'nombre' => $nombre]);
            DB::table('catalogos.producto_presentaciones')->insert([
                'id_precio' => $id, 'id_producto' => $id, 'id_presentacion' => 1,
                'factor_conversion' => 1, 'precio_venta' => 5,
            ]);
            DB::table('inventario.kardex')->insert([
                'id_producto' => $id, 'id_sucursal' => 1, 'cantidad_entrada' => 10, 'cantidad_salida' => 0,
                'stock_actual' => 10, 'precio_costo' => 2, 'ultima_actualizacion' => now(),
            ]);
        }
    }

    public function test_movimiento_de_existencia_bloquea_el_producto_antes_de_leer_kardex(): void
    {
        $consultas = [];
        DB::listen(function ($query) use (&$consultas): void {
            $consultas[] = $query->sql;
        });

        app(GestionarExistenciaService::class)->increase(ProductoPresentacion::with('producto')->find(10), 1, 5, new OrigenMovimiento(OrigenMovimiento::INGRESO, 1, 'ING-000001', 1), 2);

        $producto = collect($consultas)->search(fn (string $sql): bool => str_contains($sql, '"catalogos"."productos"'));
        $kardex = collect($consultas)->search(fn (string $sql): bool => str_contains($sql, 'select') && str_contains($sql, '"inventario"."kardex"'));

        $this->assertNotFalse($producto, 'Debe bloquearse la fila del producto.');
        $this->assertNotFalse($kardex);
        $this->assertLessThan($kardex, $producto);
        $this->assertEquals(15, (float) Kardex::query()->where('id_producto', 10)->latest('id_kardex')->value('stock_actual'));
    }
}
