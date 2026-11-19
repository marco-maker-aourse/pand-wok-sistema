<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — precios por sucursal y datos de la carta pública.
 *
 * - restaurante.precios_sucursal: cada sucursal puede tener su propio precio para la misma
 *   presentación, o no venderla (disponible = false). Sin fila, rige el precio general de
 *   inventario.precio_producto. Así el catálogo sigue siendo único y compartido.
 * - inventario.sucursales.slug / horario_atencion: identificador público estable de la
 *   sucursal para la carta digital (p. ej. "chilca") y su horario.
 * - catalogos.productos.codigo_carta: número que el cliente le dice al mozo ("el 05").
 *   catalogos.productos.codigo_externo: id del plato en la carta de origen, para que una
 *   importación repetida actualice en lugar de duplicar.
 * - restaurante.categorias_carta: presentación de cada categoría en la carta (orden,
 *   ícono, nota u oferta) sin alterar el catálogo de categorías existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario.sucursales', function (Blueprint $table): void {
            $table->string('slug', 40)->nullable()->unique();
            $table->string('horario_atencion', 80)->nullable();
        });
        DB::statement("ALTER TABLE inventario.sucursales ADD CONSTRAINT sucursales_slug_check CHECK (slug IS NULL OR slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        Schema::table('catalogos.productos', function (Blueprint $table): void {
            $table->string('codigo_carta', 10)->nullable();
            $table->string('codigo_externo', 60)->nullable()->unique();
        });

        Schema::create('restaurante.precios_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio')->cascadeOnDelete();
            $table->decimal('precio_venta', 14, 2)->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestampsTz();

            $table->primary(['id_sucursal', 'id_precio']);
            $table->index('id_precio', 'precios_sucursal_precio_idx');
        });
        // Si se vende en la sucursal, debe tener precio; si no se vende, el precio es irrelevante.
        DB::statement('ALTER TABLE restaurante.precios_sucursal ADD CONSTRAINT precios_sucursal_precio_check CHECK ((NOT disponible) OR (precio_venta IS NOT NULL AND precio_venta >= 0))');

        Schema::create('restaurante.categorias_carta', function (Blueprint $table): void {
            $table->foreignId('id_categoria')->primary()->constrained('catalogos.categorias', 'id_categoria')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('icono', 40)->nullable();
            $table->string('nota', 255)->nullable();
            $table->boolean('visible')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante.categorias_carta');
        Schema::dropIfExists('restaurante.precios_sucursal');

        Schema::table('catalogos.productos', function (Blueprint $table): void {
            $table->dropUnique(['codigo_externo']);
            $table->dropColumn(['codigo_carta', 'codigo_externo']);
        });

        DB::statement('ALTER TABLE inventario.sucursales DROP CONSTRAINT IF EXISTS sucursales_slug_check');
        Schema::table('inventario.sucursales', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'horario_atencion']);
        });
    }
};
