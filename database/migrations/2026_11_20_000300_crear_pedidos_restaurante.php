<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — pedidos (cuentas), sus ítems, modificadores elegidos e historial.
 *
 * Un pedido no es una venta: vive mientras la mesa está abierta y al cobrarse genera
 * una o varias operaciones.ventas con el servicio existente (Etapa 6).
 *
 * Garantías en base de datos:
 * - Una sola cuenta ABIERTA por mesa (índice único parcial).
 * - clave_idempotencia única en pedidos e ítems: un reintento no duplica nada.
 * - Una mesa solo puede pertenecer a un pedido de su misma sucursal (FK compuesta).
 * - cantidad_cobrada nunca supera la cantidad del ítem.
 * - Precios y nombres se congelan en el ítem; un cambio de carta no altera cuentas abiertas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante.pedidos', function (Blueprint $table): void {
            $table->bigIncrements('id_pedido');
            $table->unsignedBigInteger('id_sucursal');
            // Número visible para cocina y clientes, correlativo por sucursal.
            $table->unsignedInteger('numero');
            $table->string('tipo', 20);
            $table->unsignedBigInteger('id_mesa')->nullable();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->foreignId('id_cliente')->nullable()->constrained('catalogos.clientes', 'id_cliente')->nullOnDelete();
            $table->unsignedSmallInteger('comensales')->nullable();
            // Para llevar / delivery.
            $table->string('nombre_contacto', 120)->nullable();
            $table->string('telefono_contacto', 20)->nullable();
            $table->string('direccion_entrega', 255)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->string('estado', 20)->default('ABIERTO');
            $table->string('clave_idempotencia', 80)->unique();
            $table->timestampTz('abierto_en')->useCurrent();
            $table->timestampTz('cerrado_en')->nullable();
            $table->foreignId('id_usuario_cierre')->nullable()->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            // Pedido que absorbió a este cuando se unieron cuentas.
            $table->unsignedBigInteger('id_pedido_destino')->nullable();
            $table->timestampsTz();

            $table->unique(['id_sucursal', 'numero'], 'pedidos_sucursal_numero_unique');
            $table->unique(['id_pedido', 'id_sucursal'], 'pedidos_id_sucursal_unique');
            $table->index(['id_sucursal', 'estado', 'abierto_en'], 'pedidos_sucursal_estado_idx');
            $table->foreign('id_sucursal', 'pedidos_sucursal_fk')
                ->references('id_sucursal')->on('inventario.sucursales')->restrictOnDelete();
            $table->foreign(['id_mesa', 'id_sucursal'], 'pedidos_mesa_sucursal_fk')
                ->references(['id_mesa', 'id_sucursal'])->on('restaurante.mesas')->restrictOnDelete();
            $table->foreign(['id_pedido_destino', 'id_sucursal'], 'pedidos_destino_sucursal_fk')
                ->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_tipo_check CHECK (tipo IN ('MESA', 'PARA_LLEVAR', 'DELIVERY'))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_estado_check CHECK (estado IN ('ABIERTO', 'CERRADO', 'ANULADO', 'UNIDO'))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_mesa_por_tipo_check CHECK ((tipo = 'MESA') = (id_mesa IS NOT NULL))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_union_check CHECK ((estado = 'UNIDO') = (id_pedido_destino IS NOT NULL))");
        DB::statement('ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_comensales_check CHECK (comensales IS NULL OR comensales BETWEEN 1 AND 100)');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pedidos_mesa_abierta_unique
            ON restaurante.pedidos (id_mesa)
            WHERE estado = 'ABIERTO' AND id_mesa IS NOT NULL
        SQL);

        Schema::create('restaurante.pedido_detalles', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle_pedido');
            $table->foreignId('id_pedido')->constrained('restaurante.pedidos', 'id_pedido')->restrictOnDelete();
            $table->foreignId('id_precio')->constrained('inventario.precio_producto', 'id_precio')->restrictOnDelete();
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->restrictOnDelete();
            // Copia del nombre al momento del pedido (comanda, precuenta e historial).
            $table->string('nombre_producto', 200);
            $table->decimal('cantidad', 10, 3);
            $table->decimal('precio_base', 14, 4);
            $table->decimal('precio_modificadores', 14, 4)->default(0);
            // precio_base + precio_modificadores; subtotal = cantidad × precio_unitario.
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->string('observacion', 255)->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->decimal('cantidad_cobrada', 10, 3)->default(0);
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            // Se enlaza a restaurante.comandas en la Etapa 5 (envío a cocina).
            $table->unsignedBigInteger('id_comanda')->nullable();
            $table->timestampTz('enviado_en')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->foreignId('id_usuario_anulacion')->nullable()->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->timestampTz('anulado_en')->nullable();
            $table->string('clave_idempotencia', 80)->nullable()->unique();
            $table->timestampsTz();

            $table->index(['id_pedido', 'estado'], 'pedido_detalles_pedido_estado_idx');
            $table->index('id_comanda', 'pedido_detalles_comanda_idx');
            $table->index('id_producto', 'pedido_detalles_producto_idx');
        });

        DB::statement("ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_estado_check CHECK (estado IN ('PENDIENTE', 'ENVIADO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'ANULADO'))");
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_cobrado_check CHECK (cantidad_cobrada >= 0 AND cantidad_cobrada <= cantidad)');
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_precios_check CHECK (precio_base >= 0 AND precio_modificadores >= 0 AND precio_unitario >= 0 AND subtotal >= 0)');
        DB::statement("ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_anulacion_check CHECK ((estado = 'ANULADO') = (anulado_en IS NOT NULL))");

        Schema::create('restaurante.pedido_detalle_modificadores', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle_modificador');
            $table->foreignId('id_detalle_pedido')->constrained('restaurante.pedido_detalles', 'id_detalle_pedido')->cascadeOnDelete();
            $table->foreignId('id_opcion')->constrained('restaurante.opciones_modificador', 'id_opcion')->restrictOnDelete();
            // Copias congeladas: el ticket y la comanda no cambian si se edita la carta.
            $table->string('nombre_grupo', 80);
            $table->string('nombre_opcion', 80);
            $table->decimal('precio_adicional', 14, 2)->default(0);

            $table->unique(['id_detalle_pedido', 'id_opcion'], 'pedido_detalle_modificadores_unique');
        });

        // Historial de solo inserción: apertura, ítems, cambios, transferencias, uniones y anulaciones.
        Schema::create('restaurante.pedido_eventos', function (Blueprint $table): void {
            $table->bigIncrements('id_evento');
            $table->foreignId('id_pedido')->constrained('restaurante.pedidos', 'id_pedido')->restrictOnDelete();
            $table->unsignedBigInteger('id_detalle_pedido')->nullable();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->jsonb('datos')->nullable();
            $table->timestampTz('creado_en')->useCurrent();

            $table->index(['id_pedido', 'creado_en'], 'pedido_eventos_pedido_idx');
            $table->foreign('id_detalle_pedido', 'pedido_eventos_detalle_fk')
                ->references('id_detalle_pedido')->on('restaurante.pedido_detalles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante.pedido_eventos');
        Schema::dropIfExists('restaurante.pedido_detalle_modificadores');
        Schema::dropIfExists('restaurante.pedido_detalles');
        Schema::dropIfExists('restaurante.pedidos');
    }
};
