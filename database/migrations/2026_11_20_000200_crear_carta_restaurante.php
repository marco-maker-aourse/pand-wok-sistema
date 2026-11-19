<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — carta: tipo de producto, modificadores y agotados por sucursal.
 *
 * - tipo_producto: PREPARADO (pasa por cocina y tendrá receta), DIRECTO (se vende tal cual,
 *   p. ej. bebida embotellada) o INSUMO (solo inventario, nunca aparece en la carta).
 *   Los productos existentes quedan como DIRECTO, que es su comportamiento actual.
 * - Los modificadores son globales como el catálogo de productos; el precio adicional
 *   se congela en el pedido al momento de tomarlo.
 * - productos_agotados: la presencia de la fila marca el producto como no disponible
 *   en esa sucursal ("86"), sin tocar el catálogo compartido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos.productos', function (Blueprint $table): void {
            $table->string('tipo_producto', 20)->default('DIRECTO');
            $table->index(['tipo_producto', 'estado'], 'productos_tipo_estado_idx');
        });
        DB::statement("ALTER TABLE catalogos.productos ADD CONSTRAINT productos_tipo_producto_check CHECK (tipo_producto IN ('PREPARADO', 'DIRECTO', 'INSUMO'))");

        Schema::create('restaurante.grupos_modificadores', function (Blueprint $table): void {
            $table->bigIncrements('id_grupo');
            $table->string('nombre', 80)->unique();
            $table->unsignedSmallInteger('seleccion_minima')->default(0);
            $table->unsignedSmallInteger('seleccion_maxima')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE restaurante.grupos_modificadores ADD CONSTRAINT grupos_modificadores_seleccion_check CHECK (seleccion_maxima >= 1 AND seleccion_minima <= seleccion_maxima)');

        Schema::create('restaurante.opciones_modificador', function (Blueprint $table): void {
            $table->bigIncrements('id_opcion');
            $table->foreignId('id_grupo')->constrained('restaurante.grupos_modificadores', 'id_grupo')->restrictOnDelete();
            $table->string('nombre', 80);
            $table->decimal('precio_adicional', 14, 2)->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['id_grupo', 'nombre'], 'opciones_modificador_grupo_nombre_unique');
        });
        DB::statement('ALTER TABLE restaurante.opciones_modificador ADD CONSTRAINT opciones_modificador_precio_check CHECK (precio_adicional >= 0)');

        Schema::create('restaurante.producto_grupo_modificador', function (Blueprint $table): void {
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_grupo')->constrained('restaurante.grupos_modificadores', 'id_grupo')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);

            $table->primary(['id_producto', 'id_grupo']);
            $table->index('id_grupo', 'producto_grupo_modificador_grupo_idx');
        });

        Schema::create('restaurante.productos_agotados', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->timestampTz('marcado_en')->useCurrent();

            $table->primary(['id_sucursal', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante.productos_agotados');
        Schema::dropIfExists('restaurante.producto_grupo_modificador');
        Schema::dropIfExists('restaurante.opciones_modificador');
        Schema::dropIfExists('restaurante.grupos_modificadores');

        DB::statement('ALTER TABLE catalogos.productos DROP CONSTRAINT IF EXISTS productos_tipo_producto_check');
        Schema::table('catalogos.productos', function (Blueprint $table): void {
            $table->dropIndex('productos_tipo_estado_idx');
            $table->dropColumn('tipo_producto');
        });
    }
};
