<?php

namespace Tests\Feature;

use App\Models\DetalleSalida;
use App\Models\Kardex;
use App\Models\Lote;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use App\Services\Salidas\RegistrarSalidaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresiones de inventario sobre SQLite en memoria.
 *
 * Los esquemas de PostgreSQL se emulan con bases adjuntas; solo se crean las
 * columnas que usan los servicios probados. Nunca toca la base de producción.
 */
class ExistenciaPorLoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalogos', 'inventario', 'operaciones', 'seguridad'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }

        DB::statement('CREATE TABLE catalogos.productos (id_producto INTEGER PRIMARY KEY, nombre TEXT, afecta_igv INTEGER DEFAULT 1, es_servicio INTEGER DEFAULT 0, usa_vencimiento INTEGER DEFAULT 0, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE catalogos.presentaciones (id_presentacion INTEGER PRIMARY KEY, nombre TEXT, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE inventario.precio_producto (id_precio INTEGER PRIMARY KEY, id_producto INTEGER, id_presentacion INTEGER, factor_conversion NUMERIC, precio_venta NUMERIC, porcentaje_utilidad NUMERIC, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE inventario.lotes (id_lote INTEGER PRIMARY KEY, id_producto INTEGER, id_sucursal INTEGER, codigo_lote TEXT, fecha_vencimiento DATE NULL, stock NUMERIC, estado INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE inventario.kardex (id_kardex INTEGER PRIMARY KEY, id_producto INTEGER, id_sucursal INTEGER, cantidad_entrada NUMERIC, cantidad_salida NUMERIC, stock_actual NUMERIC, precio_costo NUMERIC, porcentaje_utilidad NUMERIC NULL, ultima_actualizacion DATETIME)');
        DB::statement('CREATE TABLE operaciones.salidas (id_salida INTEGER PRIMARY KEY, id_sucursal INTEGER, id_usuario INTEGER, motivo TEXT, observacion TEXT NULL, fecha_salida DATETIME, estado TEXT)');
        DB::statement('CREATE TABLE operaciones.detalle_salidas (id_detalle INTEGER PRIMARY KEY, id_salida INTEGER, id_precio INTEGER, id_lote INTEGER NULL, cantidad NUMERIC)');
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, nombres TEXT)');

        DB::table('catalogos.presentaciones')->insert(['id_presentacion' => 1, 'nombre' => 'UNIDAD']);
        DB::table('seguridad.usuarios')->insert(['id_usuario' => 1, 'nombres' => 'ALMACENERO']);

        // Dos productos con vencimiento que comparten el mismo código de lote en la sucursal.
        foreach ([10 => 'ARROZ', 20 => 'AZUCAR'] as $id => $nombre) {
            DB::table('catalogos.productos')->insert(['id_producto' => $id, 'nombre' => $nombre, 'usa_vencimiento' => 1]);
            DB::table('inventario.precio_producto')->insert([
                'id_precio' => $id, 'id_producto' => $id, 'id_presentacion' => 1,
                'factor_conversion' => 1, 'precio_venta' => 5, 'porcentaje_utilidad' => 0,
            ]);
            DB::table('inventario.kardex')->insert([
                'id_producto' => $id, 'id_sucursal' => 1, 'cantidad_entrada' => 10, 'cantidad_salida' => 0,
                'stock_actual' => 10, 'precio_costo' => 2, 'ultima_actualizacion' => now(),
            ]);
        }
        // El lote del otro producto se inserta primero para que una búsqueda sin producto lo elija.
        DB::table('inventario.lotes')->insert(['id_lote' => 1, 'id_producto' => 20, 'id_sucursal' => 1, 'codigo_lote' => 'L-001', 'stock' => 10]);
        DB::table('inventario.lotes')->insert(['id_lote' => 2, 'id_producto' => 10, 'id_sucursal' => 1, 'codigo_lote' => 'L-001', 'stock' => 10]);
    }

    public function test_salida_asocia_el_lote_del_producto_aunque_otro_comparta_el_codigo(): void
    {
        app(RegistrarSalidaService::class)->execute([
            'id_sucursal' => 1,
            'id_usuario' => 1,
            'motivo' => 'MERMA',
            'detalles' => [['id_prod_pres' => 10, 'cantidad' => 3, 'lote' => 'L-001']],
        ]);

        $this->assertSame(2, (int) DetalleSalida::query()->value('id_lote'));
        $this->assertEquals(7, (float) Lote::find(2)->stock);
        $this->assertEquals(10, (float) Lote::find(1)->stock);
        $this->assertEquals(7, (float) Kardex::query()->where('id_producto', 10)->latest('id_kardex')->value('stock_actual'));
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
