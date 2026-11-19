<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE operaciones.trabajos_impresion DROP CONSTRAINT trabajos_impresion_estado_check');
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_estado_check CHECK (estado IN ('PENDIENTE', 'ENTREGADO_AGENTE', 'ENVIADO_SO', 'CONFIRMADO', 'FALLIDO', 'REQUIERE_REVISION', 'CANCELADO'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE operaciones.trabajos_impresion SET estado = 'FALLIDO' WHERE estado = 'REQUIERE_REVISION'");
        DB::statement('ALTER TABLE operaciones.trabajos_impresion DROP CONSTRAINT trabajos_impresion_estado_check');
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_estado_check CHECK (estado IN ('PENDIENTE', 'ENTREGADO_AGENTE', 'ENVIADO_SO', 'CONFIRMADO', 'FALLIDO', 'CANCELADO'))");
    }
};
