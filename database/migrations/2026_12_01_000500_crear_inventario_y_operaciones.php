<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kardex, caja, compras, ventas, pagos y cola de impresión. */
return new class extends Migration {
    public function up(): void
    {
        /*
         * Movimientos de existencias por producto y sucursal. stock_actual guarda el saldo
         * acumulado tras cada movimiento y precio_costo el costo promedio vigente: así se
         * lee el saldo con una sola fila y queda la valorización histórica.
         */
        Schema::create('inventario.kardex', function (Blueprint $table): void {
            $table->bigIncrements('id_kardex');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->decimal('cantidad_entrada', 14, 4)->default(0);
            $table->decimal('cantidad_salida', 14, 4)->default(0);
            $table->decimal('stock_actual', 14, 4);
            $table->decimal('precio_costo', 14, 4)->default(0);
            // VENTA, INGRESO, AJUSTE, CONSUMO_RECETA, REVERSION_RECETA o MERMA.
            $table->string('tipo_operacion', 20)->nullable();
            // Id de la venta, ingreso, consumo o merma (sin FK: la tabla depende del tipo).
            $table->unsignedBigInteger('id_referencia')->nullable();
            // Documento tal como se muestra en el reporte: B001-00000015, F001-123, MERMA-3...
            $table->string('documento', 60)->nullable();
            $table->foreignId('id_usuario')->nullable()->constrained('seguridad.usuarios', 'id_usuario')->nullOnDelete();
            $table->timestampTz('ultima_actualizacion')->useCurrent();
            $table->index(['id_producto', 'id_sucursal', 'ultima_actualizacion']);
            $table->index(['tipo_operacion', 'id_referencia']);
        });
        // Un consumo, su reversión o una merma mueven el stock una sola vez.
        DB::statement("CREATE UNIQUE INDEX kardex_restaurante_origen_unique ON inventario.kardex (id_sucursal, tipo_operacion, id_referencia) WHERE tipo_operacion IN ('CONSUMO_RECETA','REVERSION_RECETA','MERMA')");

        Schema::create('operaciones.turnos_caja', function (Blueprint $table): void {
            $table->bigIncrements('id_turno');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->unsignedBigInteger('id_terminal')->nullable();
            $table->decimal('monto_apertura', 14, 2);
            $table->decimal('monto_cierre_declarado', 14, 2)->nullable();
            $table->timestampTz('fecha_apertura')->useCurrent();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->string('estado', 20)->default('ABIERTO');
            $table->index(['id_sucursal', 'estado']);
            $table->index(['id_sucursal', 'id_terminal'], 'turnos_caja_sucursal_terminal_idx');
            // La terminal pertenece a la misma sucursal del turno.
            $table->foreign(['id_terminal', 'id_sucursal'], 'turnos_caja_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])->on('catalogos.terminales_pos')->restrictOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX turnos_caja_unico_abierto_usuario_sucursal ON operaciones.turnos_caja (id_usuario, id_sucursal) WHERE estado = 'ABIERTO'");

        Schema::create('operaciones.movimientos_caja', function (Blueprint $table): void {
            $table->bigIncrements('id_movimiento');
            $table->foreignId('id_turno')->constrained('operaciones.turnos_caja', 'id_turno')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->string('tipo_movimiento', 30);
            $table->decimal('monto', 14, 2);
            $table->string('motivo', 255);
            $table->timestampTz('fecha_movimiento')->useCurrent();
            $table->index(['id_turno', 'fecha_movimiento']);
        });

        // Compras de insumos y productos a proveedores.
        Schema::create('operaciones.ingresos', function (Blueprint $table): void {
            $table->bigIncrements('id_ingreso');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
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

        Schema::create('operaciones.detalle_ingresos', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle');
            $table->foreignId('id_ingreso')->constrained('operaciones.ingresos', 'id_ingreso')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('catalogos.producto_presentaciones', 'id_precio');
            $table->decimal('cantidad', 14, 4);
            // Copia del factor al comprar: la conversión de la presentación puede cambiar después.
            $table->decimal('factor_conversion', 14, 4)->default(1);
            $table->decimal('precio_costo', 14, 4);
            $table->decimal('subtotal', 14, 2);
        });

        /*
         * Comprobante emitido. Los totales se guardan aunque se puedan sumar desde el detalle:
         * son la foto fiscal enviada a SUNAT y no deben cambiar.
         */
        Schema::create('operaciones.ventas', function (Blueprint $table): void {
            $table->bigIncrements('id_venta');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
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
            // Cobro de un pedido del restaurante (idempotente por clave).
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->string('clave_cobro', 80)->nullable();
            $table->string('huella_cobro', 64)->nullable();
            $table->unique(['id_sucursal', 'id_tipo_comprobante', 'serie', 'correlativo'], 'ventas_comprobante_unique');
            $table->unique(['id_pedido', 'clave_cobro'], 'ventas_pedido_cobro_unique');
            $table->index(['id_sucursal', 'fecha_emision']);
            $table->foreign(['id_pedido', 'id_sucursal'], 'ventas_pedido_sucursal_fk')
                ->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE operaciones.ventas ADD CONSTRAINT ventas_cobro_pedido_check CHECK ((id_pedido IS NULL AND clave_cobro IS NULL AND huella_cobro IS NULL) OR (id_pedido IS NOT NULL AND clave_cobro IS NOT NULL AND huella_cobro IS NOT NULL))');
        DB::statement('CREATE INDEX ventas_sucursal_fecha_estado_idx ON operaciones.ventas (id_sucursal, fecha_emision DESC, estado_sunat)');
        DB::statement('CREATE INDEX ventas_restaurante_fecha_idx ON operaciones.ventas (id_sucursal, fecha_emision) WHERE id_pedido IS NOT NULL');

        Schema::create('operaciones.detalle_ventas', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle');
            $table->foreignId('id_venta')->constrained('operaciones.ventas', 'id_venta')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('catalogos.producto_presentaciones', 'id_precio');
            $table->foreignId('id_detalle_pedido')->nullable()->constrained('restaurante.pedido_detalles', 'id_detalle_pedido')->restrictOnDelete();
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->index('id_detalle_pedido');
        });

        Schema::create('operaciones.pagos_ventas', function (Blueprint $table): void {
            $table->bigIncrements('id_pago');
            $table->foreignId('id_venta')->constrained('operaciones.ventas', 'id_venta')->cascadeOnDelete();
            $table->foreignId('id_metodo')->constrained('catalogos.metodos_pago', 'id_metodo');
            $table->decimal('monto', 14, 2);
            // El neto (monto − comisión) se calcula al consultar.
            $table->decimal('comision_pasarela', 14, 2)->default(0);
            $table->decimal('monto_recibido', 14, 2)->nullable();
            $table->decimal('vuelto', 14, 2)->nullable();
            $table->string('nro_referencia', 100)->nullable();
            $table->string('estado_pago', 20)->default('COMPLETADO');
            $table->timestampTz('fecha_pago')->useCurrent();
            $table->index(['id_metodo', 'fecha_pago']);
            $table->index(['id_venta', 'id_metodo'], 'pagos_ventas_venta_metodo_idx');
        });
        DB::statement('ALTER TABLE operaciones.pagos_ventas ADD CONSTRAINT pagos_ventas_comision_check CHECK (comision_pasarela >= 0 AND comision_pasarela <= monto)');

        // Cola de impresión: tickets por agente de terminal, comandas también por CloudPRNT.
        Schema::create('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->bigIncrements('id_trabajo_impresion');
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_terminal')->nullable();
            $table->unsignedBigInteger('id_impresora_cloudprnt')->nullable();
            $table->foreignId('id_venta')->nullable()->constrained('operaciones.ventas', 'id_venta')->restrictOnDelete();
            $table->foreignId('id_comanda')->nullable()->constrained('restaurante.comandas', 'id_comanda')->restrictOnDelete();
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
            $table->index('id_comanda', 'trabajos_impresion_comanda_idx');
            $table->index(['id_impresora_cloudprnt', 'estado', 'id_trabajo_impresion'], 'trabajos_cloudprnt_cola_idx');
            $table->foreign(['id_terminal', 'id_sucursal'], 'trabajos_impresion_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])->on('catalogos.terminales_pos')->restrictOnDelete();
            $table->foreign(['id_impresora_cloudprnt', 'id_sucursal'], 'trabajos_cloudprnt_impresora_fk')
                ->references(['id_impresora', 'id_sucursal'])->on('catalogos.impresoras')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_tipo_check CHECK (tipo IN ('TICKET_VENTA', 'PRUEBA', 'DIAGNOSTICO', 'COMANDA'))");
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_estado_check CHECK (estado IN ('PENDIENTE', 'ENTREGADO_AGENTE', 'ENVIADO_SO', 'CONFIRMADO', 'FALLIDO', 'REQUIERE_REVISION', 'CANCELADO'))");
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_transporte_check CHECK ((id_terminal IS NOT NULL AND id_impresora_cloudprnt IS NULL) OR (id_terminal IS NULL AND id_impresora_cloudprnt IS NOT NULL AND tipo = 'COMANDA' AND id_comanda IS NOT NULL))");
    }

    public function down(): void
    {
        foreach (['operaciones.trabajos_impresion', 'operaciones.pagos_ventas', 'operaciones.detalle_ventas', 'operaciones.ventas',
            'operaciones.detalle_ingresos', 'operaciones.ingresos', 'operaciones.movimientos_caja', 'operaciones.turnos_caja',
            'inventario.kardex'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
