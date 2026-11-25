<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Empresa única, sus sucursales con sus series, y la seguridad (usuarios, roles, permisos). */
return new class extends Migration {
    public function up(): void
    {
        // Datos fiscales e identidad visual: comunes a todas las sucursales.
        Schema::create('configuracion.empresa', function (Blueprint $table): void {
            $table->bigIncrements('id_empresa');
            $table->string('ruc_empresa', 11)->unique();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->text('direccion_fiscal')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->decimal('igv_porcentaje', 5, 2)->default(18);
            $table->decimal('uit_valor', 12, 2)->nullable();
            $table->text('pie_ticket')->nullable();
            $table->string('color_primario', 20)->nullable()->default('#0e7490');
            $table->string('color_secundario', 20)->nullable()->default('#0f172a');
            $table->string('texto_login_titulo', 150)->nullable();
            $table->string('texto_login_descripcion', 255)->nullable();
            $table->timestampsTz();
        });
        // El sistema atiende a una sola empresa: la tabla admite una sola fila.
        DB::statement('CREATE UNIQUE INDEX empresa_fila_unica ON configuracion.empresa ((true))');

        Schema::create('configuracion.sucursales', function (Blueprint $table): void {
            $table->bigIncrements('id_sucursal');
            $table->string('nombre', 100);
            $table->text('direccion');
            $table->string('telefono', 20)->nullable();
            $table->string('responsable', 150)->nullable();
            // Código SUNAT del establecimiento anexo (0000 = domicilio fiscal).
            $table->string('codigo_establecimiento', 4)->nullable()->unique();
            // Carta pública: identificador estable en la URL (p. ej. "chilca") y horario visible.
            $table->string('slug', 40)->nullable()->unique();
            $table->string('horario_atencion', 80)->nullable();
            $table->boolean('estado')->default(true);
        });
        DB::statement("ALTER TABLE configuracion.sucursales ADD CONSTRAINT sucursales_slug_check CHECK (slug IS NULL OR slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        // Única fuente de numeración: cada sucursal emite con sus propias series.
        Schema::create('configuracion.series_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->primary()->constrained('configuracion.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->string('serie_boleta', 4)->nullable()->unique();
            $table->unsignedInteger('ult_nro_boleta')->default(0);
            $table->string('serie_factura', 4)->nullable()->unique();
            $table->unsignedInteger('ult_nro_factura')->default(0);
            $table->string('serie_guia', 4)->nullable()->unique();
            $table->timestampsTz();
        });

        Schema::create('seguridad.roles', function (Blueprint $table): void {
            $table->bigIncrements('id_rol');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });

        Schema::create('seguridad.permisos', function (Blueprint $table): void {
            $table->bigIncrements('id_permiso');
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 100);
            $table->string('modulo', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });

        Schema::create('seguridad.rol_permisos', function (Blueprint $table): void {
            $table->foreignId('id_rol')->constrained('seguridad.roles', 'id_rol')->cascadeOnDelete();
            $table->foreignId('id_permiso')->constrained('seguridad.permisos', 'id_permiso')->cascadeOnDelete();
            $table->primary(['id_rol', 'id_permiso']);
        });

        Schema::create('seguridad.usuarios', function (Blueprint $table): void {
            $table->bigIncrements('id_usuario');
            $table->foreignId('id_rol')->constrained('seguridad.roles', 'id_rol');
            // Sede principal del usuario; las adicionales están en usuario_sucursales.
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('num_documento', 15)->unique();
            $table->string('nombres', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('pin_cajero', 255)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->rememberToken();
        });

        // Sucursales adicionales a la sede principal (nunca la repite).
        Schema::create('seguridad.usuario_sucursales', function (Blueprint $table): void {
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->primary(['id_usuario', 'id_sucursal']);
        });
    }

    public function down(): void
    {
        foreach (['seguridad.usuario_sucursales', 'seguridad.usuarios', 'seguridad.rol_permisos', 'seguridad.permisos', 'seguridad.roles',
            'configuracion.series_sucursal', 'configuracion.sucursales', 'configuracion.empresa'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
