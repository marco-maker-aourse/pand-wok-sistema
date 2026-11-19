<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** PostgreSQL no permite CREATE INDEX CONCURRENTLY dentro de una transacción. */
    public $withinTransaction = false;

    public function up(): void
    {
        // Kardex ya tiene (id_producto, id_sucursal, ultima_actualizacion) y
        // precio_producto tiene UNIQUE (id_producto, id_presentacion): no se duplican.
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ventas_sucursal_fecha_estado_idx ON operaciones.ventas (id_sucursal, fecha_emision DESC, estado_sunat)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS pagos_ventas_venta_metodo_idx ON operaciones.pagos_ventas (id_venta, id_metodo)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS operaciones.ventas_sucursal_fecha_estado_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS operaciones.pagos_ventas_venta_metodo_idx');
    }
};
