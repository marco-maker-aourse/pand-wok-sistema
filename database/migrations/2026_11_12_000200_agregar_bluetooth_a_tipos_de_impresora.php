<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE catalogos.impresoras DROP CONSTRAINT impresoras_tipo_conexion_check');
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_tipo_conexion_check CHECK (tipo_conexion IN ('USB', 'RED', 'SISTEMA', 'BLUETOOTH'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE catalogos.impresoras SET tipo_conexion = 'SISTEMA' WHERE tipo_conexion = 'BLUETOOTH'");
        DB::statement('ALTER TABLE catalogos.impresoras DROP CONSTRAINT impresoras_tipo_conexion_check');
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_tipo_conexion_check CHECK (tipo_conexion IN ('USB', 'RED', 'SISTEMA'))");
    }
};
