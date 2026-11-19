<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos.terminales_pos', function (Blueprint $table): void {
            // El secreto nunca se persiste en texto plano. El agente lo recibe una sola vez al vincularse.
            $table->string('token_agente_hash', 64)->nullable()->unique();
            $table->string('token_agente_ultimo4', 4)->nullable();
            $table->timestampTz('vinculado_en')->nullable();
            $table->timestampTz('ultimo_contacto_en')->nullable();
            $table->string('version_agente', 30)->nullable();
        });

        Schema::create('seguridad.codigos_vinculacion_agente', function (Blueprint $table): void {
            $table->bigIncrements('id_codigo_vinculacion');
            $table->unsignedBigInteger('id_terminal');
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_creador');
            $table->string('codigo_hash', 64)->unique();
            $table->timestampTz('expira_en');
            $table->timestampTz('usado_en')->nullable();
            $table->string('identificador_agente', 150)->nullable();
            $table->timestampsTz();

            $table->index(['id_terminal', 'expira_en'], 'codigos_vinculacion_terminal_expira_idx');
            $table->foreign(['id_terminal', 'id_sucursal'], 'codigos_vinculacion_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])
                ->on('catalogos.terminales_pos')
                ->restrictOnDelete();
            $table->foreign('id_usuario_creador', 'codigos_vinculacion_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuarios')->restrictOnDelete();
        });

        Schema::create('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->bigIncrements('id_trabajo_impresion');
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_terminal');
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->string('tipo', 30);
            $table->string('estado', 30)->default('PENDIENTE');
            $table->string('clave_idempotencia', 120)->unique();
            $table->jsonb('contenido');
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->unsignedSmallInteger('max_intentos')->default(3);
            $table->timestampTz('disponible_desde')->useCurrent();
            $table->timestampTz('reclamado_en')->nullable();
            $table->timestampTz('procesado_en')->nullable();
            $table->string('resultado_dispositivo', 30)->nullable();
            $table->text('detalle_error')->nullable();
            $table->timestampsTz();

            $table->index(['id_terminal', 'estado', 'disponible_desde'], 'trabajos_impresion_reclamo_idx');
            $table->index(['id_venta', 'tipo'], 'trabajos_impresion_venta_tipo_idx');
            $table->foreign(['id_terminal', 'id_sucursal'], 'trabajos_impresion_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])
                ->on('catalogos.terminales_pos')
                ->restrictOnDelete();
            $table->foreign('id_venta', 'trabajos_impresion_venta_fk')
                ->references('id_venta')->on('operaciones.ventas')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_tipo_check CHECK (tipo IN ('TICKET_VENTA', 'PRUEBA', 'DIAGNOSTICO'))");
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_estado_check CHECK (estado IN ('PENDIENTE', 'ENTREGADO_AGENTE', 'ENVIADO_SO', 'CONFIRMADO', 'FALLIDO', 'CANCELADO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones.trabajos_impresion');
        Schema::dropIfExists('seguridad.codigos_vinculacion_agente');

        Schema::table('catalogos.terminales_pos', function (Blueprint $table): void {
            $table->dropUnique(['token_agente_hash']);
            $table->dropColumn(['token_agente_hash', 'token_agente_ultimo4', 'vinculado_en', 'ultimo_contacto_en', 'version_agente']);
        });
    }
};
