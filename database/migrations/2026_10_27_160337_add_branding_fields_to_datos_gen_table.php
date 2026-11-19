<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones.datos_gen', function (Blueprint $table) {
            if (!Schema::hasColumn('operaciones.datos_gen', 'color_primario')) {
                $table->string('color_primario', 20)->nullable()->default('#0e7490');
            }
            if (!Schema::hasColumn('operaciones.datos_gen', 'color_secundario')) {
                $table->string('color_secundario', 20)->nullable()->default('#0f172a');
            }
            if (!Schema::hasColumn('operaciones.datos_gen', 'texto_login_titulo')) {
                $table->string('texto_login_titulo', 150)->nullable()->default('Bienvenido al Sistema');
            }
            if (!Schema::hasColumn('operaciones.datos_gen', 'texto_login_descripcion')) {
                $table->string('texto_login_descripcion', 255)->nullable()->default('Plataforma comercial y punto de venta centralizado.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operaciones.datos_gen', function (Blueprint $table) {
            $table->dropColumn([
                'color_primario',
                'color_secundario',
                'texto_login_titulo',
                'texto_login_descripcion',
            ]);
        });
    }
};