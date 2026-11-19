<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos.impresoras', function (Blueprint $table): void {
            $table->bigIncrements('id_impresora');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('tipo_conexion', 20);
            $table->string('identificador', 255);
            $table->unsignedInteger('puerto')->nullable();
            $table->unsignedSmallInteger('ancho_papel_mm');
            $table->boolean('soporta_escpos')->default(false);
            $table->boolean('corte_automatico')->default(false);
            $table->boolean('abre_cajon')->default(false);
            $table->boolean('activo')->default(true);
            $table->string('estado_conexion', 20)->default('desconocido');
            $table->timestampTz('ultima_verificacion')->nullable();
            $table->text('ultimo_error_conexion')->nullable();
            $table->timestampTz('ultima_impresion')->nullable();
            $table->string('ultimo_resultado_impresion', 20)->nullable();
            $table->text('ultimo_error_impresion')->nullable();
            $table->timestampsTz();

            // Permite que las FKs compuestas validen la pertenencia a sucursal.
            $table->unique(['id_impresora', 'id_sucursal'], 'impresoras_id_sucursal_unique');
            $table->index(['id_sucursal', 'activo'], 'impresoras_sucursal_activo_idx');
        });

        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_tipo_conexion_check CHECK (tipo_conexion IN ('USB', 'RED', 'SISTEMA'))");
        DB::statement('ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_ancho_papel_check CHECK (ancho_papel_mm IN (58, 80))');
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_estado_conexion_check CHECK (estado_conexion IN ('desconocido', 'conectada', 'desconectada', 'error'))");
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_resultado_impresion_check CHECK (ultimo_resultado_impresion IS NULL OR ultimo_resultado_impresion IN ('correcta', 'fallida', 'desconocida'))");

        Schema::create('catalogos.terminales_pos', function (Blueprint $table): void {
            $table->bigIncrements('id_terminal');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->restrictOnDelete();
            $table->unsignedBigInteger('id_impresora')->nullable();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('identificador_equipo', 150)->nullable();
            $table->boolean('impresion_automatica')->default(false);
            $table->unsignedSmallInteger('copias_ticket')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['id_terminal', 'id_sucursal'], 'terminales_pos_id_sucursal_unique');
            $table->index(['id_sucursal', 'activo'], 'terminales_pos_sucursal_activo_idx');
            $table->foreign(['id_impresora', 'id_sucursal'], 'terminales_pos_impresora_sucursal_fk')
                ->references(['id_impresora', 'id_sucursal'])
                ->on('catalogos.impresoras')
                ->restrictOnDelete();
        });
        DB::statement('ALTER TABLE catalogos.terminales_pos ADD CONSTRAINT terminales_pos_copias_ticket_check CHECK (copias_ticket BETWEEN 1 AND 5)');

        Schema::table('operaciones.turnos_caja', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_terminal')->nullable()->after('id_sucursal');
            // La relación compuesta evita vincular un turno con una terminal de otra sede.
            $table->foreign(['id_terminal', 'id_sucursal'], 'turnos_caja_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])
                ->on('catalogos.terminales_pos')
                ->restrictOnDelete();
            $table->index(['id_sucursal', 'id_terminal'], 'turnos_caja_sucursal_terminal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones.turnos_caja', function (Blueprint $table): void {
            $table->dropForeign('turnos_caja_terminal_sucursal_fk');
            $table->dropIndex('turnos_caja_sucursal_terminal_idx');
            $table->dropColumn('id_terminal');
        });

        Schema::dropIfExists('catalogos.terminales_pos');
        Schema::dropIfExists('catalogos.impresoras');
    }
};
