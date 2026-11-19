<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seguridad.usuarios', function (Blueprint $table) {
            $table->bigIncrements('id_usuario');
            $table->foreignId('id_rol')->constrained('seguridad.roles', 'id_rol');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('id_ubigeo', 6)->nullable();
            $table->string('num_documento', 15)->unique();
            $table->string('nombres', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('pin_cajero', 255)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->rememberToken();
            $table->foreign('id_ubigeo')->references('id_ubigeo')->on('inventario.ubigeo')->nullOnDelete();
        });
        Schema::create('seguridad.usuario_sucursales', function (Blueprint $table) {
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->primary(['id_usuario', 'id_sucursal']);
        });
        Schema::create('catalogos.clientes', function (Blueprint $table) {
            $table->bigIncrements('id_cliente');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('id_ubigeo', 6)->nullable();
            $table->string('num_documento', 15)->unique();
            $table->string('nombre_razon_social', 200);
            $table->text('direccion_fiscal')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('id_ubigeo')->references('id_ubigeo')->on('inventario.ubigeo')->nullOnDelete();
        });
        Schema::create('catalogos.proveedores', function (Blueprint $table) {
            $table->bigIncrements('id_proveedor');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('id_ubigeo', 6)->nullable();
            $table->string('num_documento', 15)->unique();
            $table->string('razon_social', 200);
            $table->text('direccion_fiscal')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('id_ubigeo')->references('id_ubigeo')->on('inventario.ubigeo')->nullOnDelete();
        });
        Schema::create('operaciones.datos_gen', function (Blueprint $table) {
            $table->bigIncrements('id_datos_gen');
            $table->foreignId('id_sucursal')->unique()->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->string('ruc_empresa', 11)->unique();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->text('direccion_fiscal')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->decimal('igv_porcentaje', 5, 2)->default(18);
            $table->decimal('uit_valor', 12, 2)->nullable();
            $table->string('serie_boleta', 4)->nullable();
            $table->unsignedBigInteger('ult_nro_boleta')->default(0);
            $table->string('serie_factura', 4)->nullable();
            $table->unsignedBigInteger('ult_nro_factura')->default(0);
            $table->string('serie_guia', 4)->nullable();
            $table->unsignedBigInteger('ult_nro_guia')->default(0);
            $table->unsignedBigInteger('ult_nro_entrada_interna')->default(0);
            $table->unsignedBigInteger('ult_nro_salida_interna')->default(0);
            $table->text('pie_ticket')->nullable();
        });
        Schema::create('catalogos.productos', function (Blueprint $table) {
            $table->bigIncrements('id_producto');
            $table->foreignId('id_categoria')->constrained('catalogos.categorias', 'id_categoria');
            $table->foreignId('id_marca')->nullable()->constrained('catalogos.marcas', 'id_marca')->nullOnDelete();
            $table->foreignId('id_unidad_base')->constrained('catalogos.unidades_medida', 'id_unidad');
            $table->string('codigo_barras', 50)->nullable()->unique();
            $table->string('codigo_sunat', 20)->nullable();
            $table->string('nombre', 200);
            $table->boolean('afecta_igv')->default(true);
            $table->boolean('es_servicio')->default(false);
            $table->boolean('usa_vencimiento')->default(false);
            $table->boolean('estado')->default(true);
            $table->index(['nombre', 'estado']);
        });
        Schema::create('inventario.precio_producto', function (Blueprint $table) {
            $table->bigIncrements('id_precio');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_presentacion')->constrained('catalogos.presentaciones', 'id_presentacion');
            $table->decimal('factor_conversion', 14, 4)->default(1);
            $table->decimal('porcentaje_utilidad', 6, 2)->nullable();
            $table->decimal('precio_venta', 14, 2);
            $table->boolean('estado')->default(true);
            $table->unique(['id_producto', 'id_presentacion']);
        });
        Schema::create('inventario.lotes', function (Blueprint $table) {
            $table->bigIncrements('id_lote');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->string('codigo_lote', 50);
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('stock', 14, 4)->default(0);
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['id_producto', 'id_sucursal', 'codigo_lote']);
            $table->index('fecha_vencimiento');
        });
        Schema::create('inventario.kardex', function (Blueprint $table) {
            $table->bigIncrements('id_kardex');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->decimal('cantidad_entrada', 14, 4)->default(0);
            $table->decimal('cantidad_salida', 14, 4)->default(0);
            $table->decimal('stock_actual', 14, 4);
            $table->decimal('precio_costo', 14, 4)->default(0);
            $table->decimal('porcentaje_utilidad', 6, 2)->nullable();
            $table->timestampTz('ultima_actualizacion')->useCurrent();
            $table->index(['id_producto', 'id_sucursal', 'ultima_actualizacion']);
        });
    }

    public function down(): void
    {
        foreach (['inventario.kardex', 'inventario.lotes', 'inventario.precio_producto', 'catalogos.productos', 'operaciones.datos_gen', 'catalogos.proveedores', 'catalogos.clientes', 'seguridad.usuario_sucursales', 'seguridad.usuarios'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
