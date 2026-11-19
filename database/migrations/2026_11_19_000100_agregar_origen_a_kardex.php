<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de cada movimiento del Kardex: qué operación lo generó, su documento y quién lo hizo.
 *
 * Solo agrega columnas opcionales: los movimientos existentes quedan intactos (con origen
 * vacío) y el código funciona antes y después de aplicar esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario.kardex', function (Blueprint $table): void {
            // VENTA, INGRESO, SALIDA o AJUSTE.
            $table->string('tipo_operacion', 20)->nullable();
            // Id de la venta, ingreso o salida (no hay clave foránea: la tabla depende del tipo).
            $table->unsignedBigInteger('id_referencia')->nullable();
            // Documento tal como se muestra en el reporte: B001-00000015, F001-123, SAL-000003...
            $table->string('documento', 60)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();

            $table->index(['tipo_operacion', 'id_referencia']);
        });
    }

    public function down(): void
    {
        Schema::table('inventario.kardex', function (Blueprint $table): void {
            $table->dropIndex(['tipo_operacion', 'id_referencia']);
            $table->dropColumn(['tipo_operacion', 'id_referencia', 'documento', 'id_usuario']);
        });
    }
};
