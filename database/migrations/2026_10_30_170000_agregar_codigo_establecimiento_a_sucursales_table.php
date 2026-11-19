<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Algunas bases iniciales ya tenían esta columna antes de registrar la migración.
        if (Schema::hasColumn('inventario.sucursales', 'codigo_establecimiento')) {
            return;
        }

        Schema::table('inventario.sucursales', function (Blueprint $table) {
            $table->string('codigo_establecimiento', 4)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventario.sucursales', 'codigo_establecimiento')) {
            return;
        }

        Schema::table('inventario.sucursales', function (Blueprint $table) {
            $table->dropColumn('codigo_establecimiento');
        });
    }
};
