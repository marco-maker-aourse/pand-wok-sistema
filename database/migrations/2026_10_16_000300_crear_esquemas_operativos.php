<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operaciones.turnos_caja', function (Blueprint $table) {
            $table->bigIncrements('id_turno');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->decimal('monto_apertura', 14, 2);
            $table->decimal('monto_cierre_declarado', 14, 2)->nullable();
            $table->timestampTz('fecha_apertura')->useCurrent();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->string('estado', 20)->default('ABIERTO');
            $table->index(['id_sucursal', 'estado']);
        });
        Schema::create('operaciones.ingresos', function (Blueprint $table) {
            $table->bigIncrements('id_ingreso');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_proveedor')->constrained('catalogos.proveedores', 'id_proveedor');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_tipo_comprobante')->constrained('catalogos.tipos_comprobante', 'id_tipo_comprobante');
            $table->string('serie_comprobante', 10)->nullable();
            $table->string('num_comprobante', 20);
            $table->date('fecha_emision');
            $table->decimal('impuesto_total', 14, 2)->default(0);
            $table->decimal('total_ingreso', 14, 2);
            $table->string('estado', 20)->default('REGISTRADO');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['id_proveedor', 'id_tipo_comprobante', 'serie_comprobante', 'num_comprobante'], 'ingresos_documento_unique');
        });
        Schema::create('operaciones.detalle_ingresos', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->foreignId('id_ingreso')->constrained('operaciones.ingresos', 'id_ingreso')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio');
            $table->foreignId('id_lote')->nullable()->constrained('inventario.lotes', 'id_lote')->nullOnDelete();
            $table->decimal('cantidad', 14, 4);
            $table->decimal('factor_conversion', 14, 4)->default(1);
            $table->decimal('precio_costo', 14, 4);
            $table->decimal('subtotal', 14, 2);
        });
        Schema::create('operaciones.salidas', function (Blueprint $table) {
            $table->bigIncrements('id_salida');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->string('motivo', 100);
            $table->text('observacion')->nullable();
            $table->timestampTz('fecha_salida')->useCurrent();
            $table->string('estado', 20)->default('REGISTRADO');
        });
        Schema::create('operaciones.detalle_salidas', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->foreignId('id_salida')->constrained('operaciones.salidas', 'id_salida')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio');
            $table->foreignId('id_lote')->nullable()->constrained('inventario.lotes', 'id_lote')->nullOnDelete();
            $table->decimal('cantidad', 14, 4);
        });
        Schema::create('operaciones.ventas', function (Blueprint $table) {
            $table->bigIncrements('id_venta');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal');
            $table->foreignId('id_cliente')->constrained('catalogos.clientes', 'id_cliente');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_turno')->constrained('operaciones.turnos_caja', 'id_turno');
            $table->foreignId('id_tipo_comprobante')->constrained('catalogos.tipos_comprobante', 'id_tipo_comprobante');
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->timestampTz('fecha_emision')->useCurrent();
            $table->decimal('op_gravadas', 14, 2)->default(0);
            $table->decimal('op_exoneradas', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('descuento_global', 14, 2)->default(0);
            $table->decimal('total_venta', 14, 2);
            $table->string('estado_sunat', 30)->default('PENDIENTE');
            $table->string('hash_cpe', 255)->nullable();
            $table->string('enlace_pdf', 255)->nullable();
            $table->string('enlace_xml', 255)->nullable();
            $table->string('enlace_cdr', 255)->nullable();
            $table->unique(['id_sucursal', 'id_tipo_comprobante', 'serie', 'correlativo'], 'ventas_comprobante_unique');
            $table->index(['id_sucursal', 'fecha_emision']);
        });
        Schema::create('operaciones.detalle_ventas', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->foreignId('id_venta')->constrained('operaciones.ventas', 'id_venta')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio');
            $table->foreignId('id_lote')->nullable()->constrained('inventario.lotes', 'id_lote')->nullOnDelete();
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
        });
        Schema::create('operaciones.pagos_ventas', function (Blueprint $table) {
            $table->bigIncrements('id_pago');
            $table->foreignId('id_venta')->constrained('operaciones.ventas', 'id_venta')->cascadeOnDelete();
            $table->foreignId('id_metodo')->constrained('catalogos.metodos_pago', 'id_metodo');
            $table->decimal('monto', 14, 2);
            $table->decimal('monto_neto', 14, 2);
            $table->decimal('comision_pasarela', 14, 2)->default(0);
            $table->string('nro_referencia', 100)->nullable();
            $table->string('estado_pago', 20)->default('COMPLETADO');
            $table->timestampTz('fecha_pago')->useCurrent();
            $table->index(['id_metodo', 'fecha_pago']);
        });
        Schema::create('operaciones.movimientos_caja', function (Blueprint $table) {
            $table->bigIncrements('id_movimiento');
            $table->foreignId('id_turno')->constrained('operaciones.turnos_caja', 'id_turno')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->string('tipo_movimiento', 30);
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 255);
            $table->timestampTz('fecha_movimiento')->useCurrent();
            $table->index(['id_turno', 'fecha_movimiento']);
        });
    }
    public function down(): void
    {
        foreach (['operaciones.movimientos_caja', 'operaciones.pagos_ventas', 'operaciones.detalle_ventas', 'operaciones.ventas', 'operaciones.detalle_salidas', 'operaciones.salidas', 'operaciones.detalle_ingresos', 'operaciones.ingresos', 'operaciones.turnos_caja'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
