<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — salón: esquema propio, áreas y mesas por sucursal.
 *
 * Las FKs compuestas (id, id_sucursal) impiden que una mesa apunte a un área
 * de otra sede, igual que en impresoras y terminales POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS restaurante');

        Schema::create('restaurante.areas', function (Blueprint $table): void {
            $table->bigIncrements('id_area');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->restrictOnDelete();
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['id_sucursal', 'nombre'], 'areas_sucursal_nombre_unique');
            $table->unique(['id_area', 'id_sucursal'], 'areas_id_sucursal_unique');
        });

        Schema::create('restaurante.mesas', function (Blueprint $table): void {
            $table->bigIncrements('id_mesa');
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_area');
            $table->string('codigo', 20);
            $table->unsignedSmallInteger('capacidad')->default(4);
            $table->string('estado', 20)->default('DISPONIBLE');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->unique(['id_sucursal', 'codigo'], 'mesas_sucursal_codigo_unique');
            $table->unique(['id_mesa', 'id_sucursal'], 'mesas_id_sucursal_unique');
            $table->index(['id_sucursal', 'estado'], 'mesas_sucursal_estado_idx');
            $table->foreign('id_sucursal', 'mesas_sucursal_fk')
                ->references('id_sucursal')->on('inventario.sucursales')->restrictOnDelete();
            $table->foreign(['id_area', 'id_sucursal'], 'mesas_area_sucursal_fk')
                ->references(['id_area', 'id_sucursal'])->on('restaurante.areas')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE restaurante.mesas ADD CONSTRAINT mesas_estado_check CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'FUERA_SERVICIO'))");
        DB::statement('ALTER TABLE restaurante.mesas ADD CONSTRAINT mesas_capacidad_check CHECK (capacidad BETWEEN 1 AND 50)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante.mesas');
        Schema::dropIfExists('restaurante.areas');
        // El esquema se elimina en la última migración del restaurante que lo usa.
    }
};
