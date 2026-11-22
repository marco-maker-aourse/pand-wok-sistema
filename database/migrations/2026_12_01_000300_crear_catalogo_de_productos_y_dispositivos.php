<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Clientes, proveedores, productos con sus presentaciones, impresoras y terminales POS. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalogos.clientes', function (Blueprint $table): void {
            $table->bigIncrements('id_cliente');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('num_documento', 15)->unique();
            $table->string('nombre_razon_social', 200);
            $table->text('direccion_fiscal')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('catalogos.proveedores', function (Blueprint $table): void {
            $table->bigIncrements('id_proveedor');
            $table->foreignId('id_tipo_documento')->constrained('catalogos.tipos_documento', 'id_tipo_documento');
            $table->string('num_documento', 15)->unique();
            $table->string('razon_social', 200);
            $table->text('direccion_fiscal')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('catalogos.productos', function (Blueprint $table): void {
            $table->bigIncrements('id_producto');
            $table->foreignId('id_categoria')->constrained('catalogos.categorias', 'id_categoria');
            $table->foreignId('id_marca')->nullable()->constrained('catalogos.marcas', 'id_marca')->nullOnDelete();
            $table->foreignId('id_unidad_base')->constrained('catalogos.unidades_medida', 'id_unidad');
            $table->string('codigo_barras', 50)->nullable()->unique();
            $table->string('codigo_sunat', 20)->nullable();
            $table->string('nombre', 200);
            // PREPARADO (pasa por cocina), DIRECTO (se vende tal cual) o INSUMO (solo inventario).
            $table->string('tipo_producto', 20)->default('DIRECTO');
            $table->boolean('afecta_igv')->default(true);
            $table->boolean('es_servicio')->default(false);
            // Número que el cliente dice al mozo y id del plato en la carta de origen (importación).
            $table->string('codigo_carta', 10)->nullable();
            $table->string('codigo_externo', 60)->nullable()->unique();
            $table->boolean('estado')->default(true);
            $table->index(['nombre', 'estado']);
            $table->index(['tipo_producto', 'estado'], 'productos_tipo_estado_idx');
        });
        DB::statement("ALTER TABLE catalogos.productos ADD CONSTRAINT productos_tipo_producto_check CHECK (tipo_producto IN ('PREPARADO', 'DIRECTO', 'INSUMO'))");

        /*
         * Variante vendible de un producto: producto × presentación con su precio general
         * (Inca Kola 500 ml, Inca Kola 1 L). La clave conserva el nombre id_precio que usan
         * pedidos, ventas, recetas y el agente de impresión.
         *
         * - factor_conversion: unidades base que contiene (caja ×12 → 12).
         * - id_producto_stock: producto cuyo inventario se mueve al vender esta variante
         *   cuando el tamaño tiene stock propio (una botella de 1 L no se convierte en 500 ml).
         */
        Schema::create('catalogos.producto_presentaciones', function (Blueprint $table): void {
            $table->bigIncrements('id_precio');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_presentacion')->constrained('catalogos.presentaciones', 'id_presentacion');
            $table->decimal('factor_conversion', 14, 4)->default(1);
            $table->decimal('precio_venta', 14, 2);
            $table->foreignId('id_producto_stock')->nullable()->constrained('catalogos.productos', 'id_producto')->restrictOnDelete();
            $table->boolean('estado')->default(true);
            $table->unique(['id_producto', 'id_presentacion']);
            $table->index('id_producto_stock', 'producto_presentaciones_stock_idx');
        });
        DB::statement('ALTER TABLE catalogos.producto_presentaciones ADD CONSTRAINT producto_presentaciones_stock_check CHECK (id_producto_stock IS NULL OR id_producto_stock <> id_producto)');
        DB::statement('ALTER TABLE catalogos.producto_presentaciones ADD CONSTRAINT producto_presentaciones_valores_check CHECK (factor_conversion > 0 AND precio_venta >= 0)');

        Schema::create('catalogos.impresoras', function (Blueprint $table): void {
            $table->bigIncrements('id_impresora');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->restrictOnDelete();
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
            // Credencial de la impresora Star CloudPRNT (solo el hash).
            $table->string('cloudprnt_clave_hash', 64)->nullable();
            $table->timestampsTz();
            // Permite que las FKs compuestas validen la pertenencia a sucursal.
            $table->unique(['id_impresora', 'id_sucursal'], 'impresoras_id_sucursal_unique');
            $table->index(['id_sucursal', 'activo'], 'impresoras_sucursal_activo_idx');
        });
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_tipo_conexion_check CHECK (tipo_conexion IN ('USB', 'RED', 'SISTEMA', 'BLUETOOTH'))");
        DB::statement('ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_ancho_papel_check CHECK (ancho_papel_mm IN (58, 80))');
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_estado_conexion_check CHECK (estado_conexion IN ('desconocido', 'conectada', 'desconectada', 'error'))");
        DB::statement("ALTER TABLE catalogos.impresoras ADD CONSTRAINT impresoras_resultado_impresion_check CHECK (ultimo_resultado_impresion IS NULL OR ultimo_resultado_impresion IN ('correcta', 'fallida', 'desconocida'))");

        Schema::create('catalogos.terminales_pos', function (Blueprint $table): void {
            $table->bigIncrements('id_terminal');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->restrictOnDelete();
            $table->unsignedBigInteger('id_impresora')->nullable();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('identificador_equipo', 150)->nullable();
            // Nombre exacto de la cola Windows, reportado por el agente de esta terminal.
            $table->string('impresora_sistema_nombre', 255)->nullable();
            $table->boolean('impresion_automatica')->default(false);
            $table->unsignedSmallInteger('copias_ticket')->default(1);
            $table->boolean('activo')->default(true);
            // El secreto del agente nunca se guarda en texto plano.
            $table->string('token_agente_hash', 64)->nullable()->unique();
            $table->string('token_agente_ultimo4', 4)->nullable();
            $table->timestampTz('vinculado_en')->nullable();
            $table->timestampTz('ultimo_contacto_en')->nullable();
            $table->string('version_agente', 30)->nullable();
            $table->timestampsTz();
            $table->unique(['id_terminal', 'id_sucursal'], 'terminales_pos_id_sucursal_unique');
            $table->index(['id_sucursal', 'activo'], 'terminales_pos_sucursal_activo_idx');
            $table->foreign(['id_impresora', 'id_sucursal'], 'terminales_pos_impresora_sucursal_fk')
                ->references(['id_impresora', 'id_sucursal'])->on('catalogos.impresoras')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE catalogos.terminales_pos ADD CONSTRAINT terminales_pos_copias_ticket_check CHECK (copias_ticket BETWEEN 1 AND 5)');

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
                ->references(['id_terminal', 'id_sucursal'])->on('catalogos.terminales_pos')->restrictOnDelete();
            $table->foreign('id_usuario_creador', 'codigos_vinculacion_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['seguridad.codigos_vinculacion_agente', 'catalogos.terminales_pos', 'catalogos.impresoras',
            'catalogos.producto_presentaciones', 'catalogos.productos', 'catalogos.proveedores', 'catalogos.clientes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
