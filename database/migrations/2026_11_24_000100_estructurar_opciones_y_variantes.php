<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — opciones estructuradas (sopa del menú, acompañamientos) y variantes con stock propio.
 *
 * Extiende el modelo de modificadores de 2026_11_20_000200 en vez de crear otro:
 *
 * - opciones_modificador.id_precio: la opción puede ser un producto real de la carta
 *   (p. ej. "Sopa wantán", "Wantán frito x3"). Así su nombre sale del producto (no se
 *   duplica), se respeta el agotado de la sucursal y se descuenta su receta o su stock.
 *   nombre pasa a ser opcional: solo se usa para opciones sin producto ("Jugoso").
 * - opciones_sucursal: recargo propio y disponibilidad de cada opción por sucursal.
 *   Sin fila rige el recargo general y la opción está disponible.
 * - producto_grupo_modificador.seleccion_minima/maxima: la obligatoriedad se puede ajustar
 *   por plato sin duplicar el grupo (la misma "Sopa del menú" sirve a varios menús).
 * - pedido_detalle_modificadores.cantidad: cuántas unidades del ítem llevan esa opción
 *   ("2 × Menú – 1 sopa wantán, 1 sopa de pollo"); id_precio congela el producto elegido.
 * - precio_producto.id_producto_stock: variante física con stock propio. Vender la
 *   presentación "1 L" de "Inca Kola" descuenta del producto de inventario "Inca Kola 1 L",
 *   no del inventario base del producto vendido.
 *
 * Compatible con datos existentes: todas las columnas nuevas son nulas o tienen valor por
 * defecto; la cantidad de los modificadores ya registrados se completa con la del ítem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurante.opciones_modificador', function (Blueprint $table): void {
            $table->foreignId('id_precio')->nullable()
                ->constrained('inventario.precio_producto', 'id_precio')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE restaurante.opciones_modificador ALTER COLUMN nombre DROP NOT NULL');
        DB::statement('ALTER TABLE restaurante.opciones_modificador ADD CONSTRAINT opciones_modificador_nombre_o_producto_check CHECK (nombre IS NOT NULL OR id_precio IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX opciones_modificador_grupo_precio_unique ON restaurante.opciones_modificador (id_grupo, id_precio) WHERE id_precio IS NOT NULL');

        Schema::create('restaurante.opciones_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_opcion')->constrained('restaurante.opciones_modificador', 'id_opcion')->cascadeOnDelete();
            $table->decimal('precio_adicional', 14, 2)->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestampsTz();

            $table->primary(['id_sucursal', 'id_opcion']);
            $table->index('id_opcion', 'opciones_sucursal_opcion_idx');
        });
        DB::statement('ALTER TABLE restaurante.opciones_sucursal ADD CONSTRAINT opciones_sucursal_precio_check CHECK (precio_adicional IS NULL OR precio_adicional >= 0)');

        Schema::table('restaurante.producto_grupo_modificador', function (Blueprint $table): void {
            $table->unsignedSmallInteger('seleccion_minima')->nullable();
            $table->unsignedSmallInteger('seleccion_maxima')->nullable();
        });
        DB::statement('ALTER TABLE restaurante.producto_grupo_modificador ADD CONSTRAINT producto_grupo_seleccion_check CHECK ((seleccion_maxima IS NULL OR seleccion_maxima >= 1) AND (seleccion_minima IS NULL OR seleccion_maxima IS NULL OR seleccion_minima <= seleccion_maxima))');

        Schema::table('restaurante.pedido_detalle_modificadores', function (Blueprint $table): void {
            $table->decimal('cantidad', 10, 3)->nullable();
            $table->foreignId('id_precio')->nullable()
                ->constrained('inventario.precio_producto', 'id_precio')->restrictOnDelete();
        });
        DB::statement('UPDATE restaurante.pedido_detalle_modificadores m SET cantidad = d.cantidad FROM restaurante.pedido_detalles d WHERE d.id_detalle_pedido = m.id_detalle_pedido');
        DB::statement('ALTER TABLE restaurante.pedido_detalle_modificadores ALTER COLUMN cantidad SET NOT NULL');
        DB::statement('ALTER TABLE restaurante.pedido_detalle_modificadores ADD CONSTRAINT pedido_detalle_modificadores_cantidad_check CHECK (cantidad > 0)');

        Schema::table('inventario.precio_producto', function (Blueprint $table): void {
            $table->foreignId('id_producto_stock')->nullable()
                ->constrained('catalogos.productos', 'id_producto')->restrictOnDelete();
            $table->index('id_producto_stock', 'precio_producto_stock_idx');
        });
        DB::statement('ALTER TABLE inventario.precio_producto ADD CONSTRAINT precio_producto_stock_check CHECK (id_producto_stock IS NULL OR id_producto_stock <> id_producto)');
    }

    public function down(): void
    {
        if (DB::table('restaurante.opciones_modificador')->whereNull('nombre')->exists()) {
            throw new RuntimeException('Hay opciones vinculadas a productos sin nombre propio; asígnales un nombre antes de revertir.');
        }
        if (DB::table('inventario.precio_producto')->whereNotNull('id_producto_stock')->exists()
            || DB::table('restaurante.opciones_sucursal')->exists()) {
            throw new RuntimeException('Hay variantes con stock propio u opciones configuradas por sucursal que deben preservarse.');
        }

        DB::statement('ALTER TABLE inventario.precio_producto DROP CONSTRAINT IF EXISTS precio_producto_stock_check');
        Schema::table('inventario.precio_producto', function (Blueprint $table): void {
            $table->dropIndex('precio_producto_stock_idx');
            $table->dropConstrainedForeignId('id_producto_stock');
        });

        DB::statement('ALTER TABLE restaurante.pedido_detalle_modificadores DROP CONSTRAINT IF EXISTS pedido_detalle_modificadores_cantidad_check');
        Schema::table('restaurante.pedido_detalle_modificadores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_precio');
            $table->dropColumn('cantidad');
        });

        DB::statement('ALTER TABLE restaurante.producto_grupo_modificador DROP CONSTRAINT IF EXISTS producto_grupo_seleccion_check');
        Schema::table('restaurante.producto_grupo_modificador', function (Blueprint $table): void {
            $table->dropColumn(['seleccion_minima', 'seleccion_maxima']);
        });

        Schema::dropIfExists('restaurante.opciones_sucursal');

        DB::statement('DROP INDEX IF EXISTS restaurante.opciones_modificador_grupo_precio_unique');
        DB::statement('ALTER TABLE restaurante.opciones_modificador DROP CONSTRAINT IF EXISTS opciones_modificador_nombre_o_producto_check');
        DB::statement('ALTER TABLE restaurante.opciones_modificador ALTER COLUMN nombre SET NOT NULL');
        Schema::table('restaurante.opciones_modificador', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_precio');
        });
    }
};
