<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos.terminales_pos', function (Blueprint $table): void {
            // Nombre exacto de la cola Windows, reportado por el agente de esta terminal.
            $table->string('impresora_sistema_nombre', 255)->nullable()->after('identificador_equipo');
        });
    }

    public function down(): void
    {
        Schema::table('catalogos.terminales_pos', function (Blueprint $table): void {
            $table->dropColumn('impresora_sistema_nombre');
        });
    }
};
