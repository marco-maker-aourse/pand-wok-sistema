<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        foreach (['seguridad', 'catalogos', 'inventario', 'operaciones'] as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }

        Schema::create('inventario.ubigeo', function (Blueprint $table) {
            $table->string('id_ubigeo', 6)->primary();
            $table->string('departamento', 100);
            $table->string('provincia', 100);
            $table->string('distrito', 100);
        });
        Schema::create('inventario.sucursales', function (Blueprint $table) {
            $table->bigIncrements('id_sucursal');
            $table->string('id_ubigeo', 6)->nullable();
            $table->string('nombre', 100);
            $table->text('direccion');
            $table->string('telefono', 20)->nullable();
            $table->string('responsable', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->foreign('id_ubigeo')->references('id_ubigeo')->on('inventario.ubigeo')->nullOnDelete();
        });
        Schema::create('seguridad.roles', function (Blueprint $table) {
            $table->bigIncrements('id_rol');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });
        Schema::create('seguridad.permisos', function (Blueprint $table) {
            $table->bigIncrements('id_permiso');
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 100);
            $table->string('modulo', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });
        Schema::create('seguridad.rol_permisos', function (Blueprint $table) {
            $table->foreignId('id_rol')->constrained('seguridad.roles', 'id_rol')->cascadeOnDelete();
            $table->foreignId('id_permiso')->constrained('seguridad.permisos', 'id_permiso')->cascadeOnDelete();
            $table->primary(['id_rol', 'id_permiso']);
        });
        Schema::create('catalogos.tipos_documento', function (Blueprint $table) {
            $table->bigIncrements('id_tipo_documento');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('longitud_exacta')->nullable();
            $table->string('tipo_persona', 20)->nullable();
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.tipos_comprobante', function (Blueprint $table) {
            $table->bigIncrements('id_tipo_comprobante');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('descripcion', 150);
            $table->boolean('exige_ruc')->default(false);
            $table->boolean('permite_anulacion')->default(true);
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.categorias', function (Blueprint $table) {
            $table->bigIncrements('id_categoria');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.unidades_medida', function (Blueprint $table) {
            $table->bigIncrements('id_unidad');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('nombre', 100);
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.marcas', function (Blueprint $table) {
            $table->bigIncrements('id_marca');
            $table->string('nombre', 100)->unique();
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.presentaciones', function (Blueprint $table) {
            $table->bigIncrements('id_presentacion');
            $table->string('nombre', 100)->unique();
            $table->boolean('estado')->default(true);
        });
        Schema::create('catalogos.metodos_pago', function (Blueprint $table) {
            $table->bigIncrements('id_metodo');
            $table->string('nombre', 100)->unique();
            $table->string('tipo', 20);
            $table->boolean('requiere_referencia')->default(false);
            $table->decimal('comision_porcentaje', 5, 2)->default(0);
            $table->decimal('comision_fija', 10, 2)->default(0);
            $table->boolean('estado')->default(true);
        });
        DB::statement("ALTER TABLE catalogos.metodos_pago ADD CONSTRAINT metodos_pago_tipo_check CHECK (tipo IN ('EFECTIVO', 'BILLETERA_DIRECTA', 'PASARELA'))");
    }

    public function down(): void
    {
        foreach (['operaciones', 'inventario', 'catalogos', 'seguridad'] as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
};
