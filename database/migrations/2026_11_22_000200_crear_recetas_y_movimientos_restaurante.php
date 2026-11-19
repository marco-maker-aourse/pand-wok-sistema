<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante.receta_insumos', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_precio_plato')->constrained('inventario.precio_producto', 'id_precio');
            $table->foreignId('id_precio_insumo')->constrained('inventario.precio_producto', 'id_precio');
            $table->decimal('cantidad', 14, 4);
            $table->primary(['id_sucursal', 'id_precio_plato', 'id_precio_insumo'], 'receta_insumos_pk');
        });
        DB::statement('ALTER TABLE restaurante.receta_insumos ADD CONSTRAINT receta_insumos_cantidad_check CHECK (cantidad > 0 AND id_precio_plato <> id_precio_insumo)');

        Schema::create('restaurante.consumos_insumos', function (Blueprint $table): void {
            $table->bigIncrements('id_consumo');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_detalle_pedido')->constrained('restaurante.pedido_detalles', 'id_detalle_pedido');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto');
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->jsonb('lotes');
            $table->string('estado', 16)->default('CONSUMIDO');
            $table->timestampTz('consumido_en');
            $table->timestampTz('resuelto_en')->nullable();
            $table->unique(['id_detalle_pedido', 'id_producto'], 'consumo_item_insumo_unique');
            $table->index(['id_sucursal', 'consumido_en']);
            $table->index(['id_sucursal', 'resuelto_en']);
        });
        DB::statement("ALTER TABLE restaurante.consumos_insumos ADD CONSTRAINT consumo_insumos_check CHECK (cantidad_base > 0 AND costo_unitario >= 0 AND estado IN ('CONSUMIDO','REVERTIDO','MERMA'))");

        Schema::create('restaurante.mermas', function (Blueprint $table): void {
            $table->bigIncrements('id_merma');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio');
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->string('motivo');
            $table->string('clave', 80);
            $table->string('huella', 64);
            $table->timestampTz('registrada_en');
            $table->unique(['id_sucursal', 'clave']);
            $table->index(['id_sucursal', 'registrada_en']);
        });
        DB::statement('ALTER TABLE restaurante.mermas ADD CONSTRAINT mermas_cantidad_check CHECK (cantidad_base > 0 AND costo_unitario >= 0)');
        DB::statement("CREATE UNIQUE INDEX kardex_restaurante_origen_unique ON inventario.kardex (id_sucursal, tipo_operacion, id_referencia) WHERE tipo_operacion IN ('CONSUMO_RECETA','REVERSION_RECETA','MERMA')");
    }

    public function down(): void
    {
        foreach (['consumos_insumos', 'mermas', 'receta_insumos'] as $tabla) {
            if (DB::table("restaurante.{$tabla}")->exists()) {
                throw new RuntimeException('Hay registros de recetas o inventario de restaurante que deben preservarse.');
            }
        }

        DB::statement('DROP INDEX inventario.kardex_restaurante_origen_unique');
        Schema::dropIfExists('restaurante.mermas');
        Schema::dropIfExists('restaurante.consumos_insumos');
        Schema::dropIfExists('restaurante.receta_insumos');
    }
};
