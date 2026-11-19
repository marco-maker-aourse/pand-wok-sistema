<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurante.pedido_detalles', function (Blueprint $table): void {
            $table->string('tipo_producto', 12)->nullable();
        });
        DB::statement("ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT detalle_pedido_tipo_producto_check CHECK (tipo_producto IN ('DIRECTO', 'PREPARADO'))");
        DB::statement("UPDATE restaurante.pedido_detalles AS d SET tipo_producto = p.tipo_producto
            FROM catalogos.productos AS p WHERE p.id_producto = d.id_producto AND p.tipo_producto IN ('DIRECTO', 'PREPARADO')");
        Schema::table('operaciones.ventas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->string('clave_cobro', 80)->nullable();
            $table->string('huella_cobro', 64)->nullable();
            $table->foreign(['id_pedido', 'id_sucursal'], 'ventas_pedido_sucursal_fk')
                ->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
            $table->unique(['id_pedido', 'clave_cobro'], 'ventas_pedido_cobro_unique');
        });
        DB::statement('ALTER TABLE operaciones.ventas ADD CONSTRAINT ventas_cobro_pedido_check CHECK ((id_pedido IS NULL AND clave_cobro IS NULL AND huella_cobro IS NULL) OR (id_pedido IS NOT NULL AND clave_cobro IS NOT NULL AND huella_cobro IS NOT NULL))');
        Schema::table('operaciones.detalle_ventas', function (Blueprint $table): void {
            $table->foreignId('id_detalle_pedido')->nullable()->constrained('restaurante.pedido_detalles', 'id_detalle_pedido')->restrictOnDelete();
            $table->index('id_detalle_pedido');
        });
    }

    public function down(): void
    {
        if (DB::table('operaciones.ventas')->whereNotNull('id_pedido')->exists()) {
            throw new RuntimeException('Hay cobros de restaurante que requieren preservar su trazabilidad.');
        }

        Schema::table('operaciones.detalle_ventas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_detalle_pedido');
        });
        DB::statement('ALTER TABLE restaurante.pedido_detalles DROP CONSTRAINT detalle_pedido_tipo_producto_check');
        Schema::table('restaurante.pedido_detalles', fn (Blueprint $table) => $table->dropColumn('tipo_producto'));
        DB::statement('ALTER TABLE operaciones.ventas DROP CONSTRAINT ventas_cobro_pedido_check');
        Schema::table('operaciones.ventas', function (Blueprint $table): void {
            $table->dropForeign('ventas_pedido_sucursal_fk');
            $table->dropUnique('ventas_pedido_cobro_unique');
            $table->dropColumn(['id_pedido', 'clave_cobro', 'huella_cobro']);
        });
    }
};
