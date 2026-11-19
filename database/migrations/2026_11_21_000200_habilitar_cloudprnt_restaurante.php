<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos.impresoras', function (Blueprint $table): void {
            $table->string('cloudprnt_clave_hash', 64)->nullable();
        });

        DB::statement('DROP INDEX restaurante.comandas_envio_estacion_unique');
        DB::statement('CREATE UNIQUE INDEX comandas_envio_estacion_unique ON restaurante.comandas (id_sucursal, clave_envio, COALESCE(id_estacion, 0))');
        DB::statement('ALTER TABLE restaurante.estaciones DROP CONSTRAINT estaciones_impresion_check');
        DB::statement('ALTER TABLE restaurante.estaciones ADD CONSTRAINT estaciones_impresion_check CHECK (id_terminal IS NULL OR id_impresora IS NOT NULL)');

        Schema::table('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_impresora_cloudprnt')->nullable();
            $table->foreign(['id_impresora_cloudprnt', 'id_sucursal'], 'trabajos_cloudprnt_impresora_fk')
                ->references(['id_impresora', 'id_sucursal'])->on('catalogos.impresoras')->restrictOnDelete();
            $table->index(['id_impresora_cloudprnt', 'estado', 'id_trabajo_impresion'], 'trabajos_cloudprnt_cola_idx');
        });
        DB::statement('ALTER TABLE operaciones.trabajos_impresion ALTER COLUMN id_terminal DROP NOT NULL');
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_transporte_check CHECK ((id_terminal IS NOT NULL AND id_impresora_cloudprnt IS NULL) OR (id_terminal IS NULL AND id_impresora_cloudprnt IS NOT NULL AND tipo = 'COMANDA' AND id_comanda IS NOT NULL))");
    }

    public function down(): void
    {
        if (DB::table('operaciones.trabajos_impresion')->whereNotNull('id_impresora_cloudprnt')->exists()
            || DB::table('restaurante.estaciones')->whereNotNull('id_impresora')->whereNull('id_terminal')->exists()) {
            throw new RuntimeException('Hay estaciones o historial de impresión CloudPRNT que requieren preservar sus datos.');
        }

        DB::statement('ALTER TABLE operaciones.trabajos_impresion DROP CONSTRAINT trabajos_impresion_transporte_check');
        DB::statement('ALTER TABLE operaciones.trabajos_impresion ALTER COLUMN id_terminal SET NOT NULL');
        Schema::table('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->dropForeign('trabajos_cloudprnt_impresora_fk');
            $table->dropIndex('trabajos_cloudprnt_cola_idx');
            $table->dropColumn('id_impresora_cloudprnt');
        });
        DB::statement('ALTER TABLE restaurante.estaciones DROP CONSTRAINT estaciones_impresion_check');
        DB::statement('ALTER TABLE restaurante.estaciones ADD CONSTRAINT estaciones_impresion_check CHECK ((id_impresora IS NULL) = (id_terminal IS NULL))');
        DB::statement('DROP INDEX restaurante.comandas_envio_estacion_unique');
        DB::statement('CREATE UNIQUE INDEX comandas_envio_estacion_unique ON restaurante.comandas (clave_envio, COALESCE(id_estacion, 0))');
        Schema::table('catalogos.impresoras', fn (Blueprint $table) => $table->dropColumn('cloudprnt_clave_hash'));
    }
};
