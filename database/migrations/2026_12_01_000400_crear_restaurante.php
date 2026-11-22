<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Salón, carta, pedidos, cocina, recetas y mermas del restaurante. */
return new class extends Migration {
    public function up(): void
    {
        $this->crearSalon();
        $this->crearCarta();
        $this->crearCocina();
        $this->crearPedidos();
        $this->crearRecetasYMermas();
    }

    private function crearSalon(): void
    {
        Schema::create('restaurante.areas', function (Blueprint $table): void {
            $table->bigIncrements('id_area');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->restrictOnDelete();
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
            $table->foreign('id_sucursal', 'mesas_sucursal_fk')->references('id_sucursal')->on('configuracion.sucursales')->restrictOnDelete();
            // La mesa pertenece a un área de su misma sucursal.
            $table->foreign(['id_area', 'id_sucursal'], 'mesas_area_sucursal_fk')->references(['id_area', 'id_sucursal'])->on('restaurante.areas')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE restaurante.mesas ADD CONSTRAINT mesas_estado_check CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'FUERA_SERVICIO'))");
        DB::statement('ALTER TABLE restaurante.mesas ADD CONSTRAINT mesas_capacidad_check CHECK (capacidad BETWEEN 1 AND 50)');
    }

    private function crearCarta(): void
    {
        // Cómo se muestra cada categoría en la carta (la categoría es del catálogo).
        Schema::create('restaurante.categorias_carta', function (Blueprint $table): void {
            $table->foreignId('id_categoria')->primary()->constrained('catalogos.categorias', 'id_categoria')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('icono', 40)->nullable();
            $table->string('nota', 255)->nullable();
            $table->boolean('visible')->default(true);
            $table->timestampsTz();
        });

        // Precio y disponibilidad de una variante en una sucursal (si no hay fila, rige el precio general).
        Schema::create('restaurante.precios_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_precio')->constrained('catalogos.producto_presentaciones', 'id_precio')->cascadeOnDelete();
            $table->decimal('precio_venta', 14, 2)->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestampsTz();
            $table->primary(['id_sucursal', 'id_precio']);
            $table->index('id_precio', 'precios_sucursal_precio_idx');
        });
        // Si se vende en la sucursal, debe tener precio; si no se vende, el precio es irrelevante.
        DB::statement('ALTER TABLE restaurante.precios_sucursal ADD CONSTRAINT precios_sucursal_precio_check CHECK ((NOT disponible) OR (precio_venta IS NOT NULL AND precio_venta >= 0))');

        // Grupos de opciones: "Término", "Elige tu sopa", "Adicionales".
        Schema::create('restaurante.grupos_modificadores', function (Blueprint $table): void {
            $table->bigIncrements('id_grupo');
            $table->string('nombre', 80)->unique();
            $table->unsignedSmallInteger('seleccion_minima')->default(0);
            $table->unsignedSmallInteger('seleccion_maxima')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE restaurante.grupos_modificadores ADD CONSTRAINT grupos_modificadores_seleccion_check CHECK (seleccion_maxima >= 1 AND seleccion_minima <= seleccion_maxima)');

        // Una opción es un texto libre ("Jugoso") o una variante de la carta (id_precio, p. ej. una sopa).
        Schema::create('restaurante.opciones_modificador', function (Blueprint $table): void {
            $table->bigIncrements('id_opcion');
            $table->foreignId('id_grupo')->constrained('restaurante.grupos_modificadores', 'id_grupo')->restrictOnDelete();
            $table->string('nombre', 80)->nullable();
            $table->foreignId('id_precio')->nullable()->constrained('catalogos.producto_presentaciones', 'id_precio')->restrictOnDelete();
            $table->decimal('precio_adicional', 14, 2)->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
            $table->unique(['id_grupo', 'nombre'], 'opciones_modificador_grupo_nombre_unique');
        });
        DB::statement('ALTER TABLE restaurante.opciones_modificador ADD CONSTRAINT opciones_modificador_precio_check CHECK (precio_adicional >= 0)');
        DB::statement('ALTER TABLE restaurante.opciones_modificador ADD CONSTRAINT opciones_modificador_nombre_o_producto_check CHECK (nombre IS NOT NULL OR id_precio IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX opciones_modificador_grupo_precio_unique ON restaurante.opciones_modificador (id_grupo, id_precio) WHERE id_precio IS NOT NULL');

        // Precio y disponibilidad de una opción en una sucursal.
        Schema::create('restaurante.opciones_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_opcion')->constrained('restaurante.opciones_modificador', 'id_opcion')->cascadeOnDelete();
            $table->decimal('precio_adicional', 14, 2)->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestampsTz();
            $table->primary(['id_sucursal', 'id_opcion']);
            $table->index('id_opcion', 'opciones_sucursal_opcion_idx');
        });
        DB::statement('ALTER TABLE restaurante.opciones_sucursal ADD CONSTRAINT opciones_sucursal_precio_check CHECK (precio_adicional IS NULL OR precio_adicional >= 0)');

        // Grupos que aplica cada producto; la selección mínima/máxima puede ajustarse por producto.
        Schema::create('restaurante.producto_grupo_modificador', function (Blueprint $table): void {
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_grupo')->constrained('restaurante.grupos_modificadores', 'id_grupo')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedSmallInteger('seleccion_minima')->nullable();
            $table->unsignedSmallInteger('seleccion_maxima')->nullable();
            $table->primary(['id_producto', 'id_grupo']);
            $table->index('id_grupo', 'producto_grupo_modificador_grupo_idx');
        });
        DB::statement('ALTER TABLE restaurante.producto_grupo_modificador ADD CONSTRAINT producto_grupo_seleccion_check CHECK ((seleccion_maxima IS NULL OR seleccion_maxima >= 1) AND (seleccion_minima IS NULL OR seleccion_maxima IS NULL OR seleccion_minima <= seleccion_maxima))');

        Schema::create('restaurante.productos_agotados', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->timestampTz('marcado_en')->useCurrent();
            $table->primary(['id_sucursal', 'id_producto']);
        });
    }

    private function crearCocina(): void
    {
        Schema::create('restaurante.estaciones', function (Blueprint $table): void {
            $table->bigIncrements('id_estacion');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal')->restrictOnDelete();
            $table->string('nombre', 60);
            $table->unsignedBigInteger('id_impresora')->nullable();
            $table->unsignedBigInteger('id_terminal')->nullable();
            $table->unsignedSmallInteger('copias')->default(1);
            $table->boolean('por_defecto')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
            $table->unique(['id_sucursal', 'nombre'], 'estaciones_sucursal_nombre_unique');
            $table->unique(['id_estacion', 'id_sucursal'], 'estaciones_id_sucursal_unique');
            $table->foreign(['id_impresora', 'id_sucursal'], 'estaciones_impresora_sucursal_fk')
                ->references(['id_impresora', 'id_sucursal'])->on('catalogos.impresoras')->restrictOnDelete();
            $table->foreign(['id_terminal', 'id_sucursal'], 'estaciones_terminal_sucursal_fk')
                ->references(['id_terminal', 'id_sucursal'])->on('catalogos.terminales_pos')->restrictOnDelete();
        });
        // Una terminal imprime a través de una impresora; CloudPRNT usa la impresora sin terminal.
        DB::statement('ALTER TABLE restaurante.estaciones ADD CONSTRAINT estaciones_impresion_check CHECK (id_terminal IS NULL OR id_impresora IS NOT NULL)');
        DB::statement('ALTER TABLE restaurante.estaciones ADD CONSTRAINT estaciones_copias_check CHECK (copias BETWEEN 1 AND 5)');
        DB::statement('CREATE UNIQUE INDEX estaciones_por_defecto_unique ON restaurante.estaciones (id_sucursal) WHERE por_defecto AND activo');

        Schema::create('restaurante.estacion_categorias', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_sucursal');
            $table->foreignId('id_categoria')->constrained('catalogos.categorias', 'id_categoria')->cascadeOnDelete();
            $table->unsignedBigInteger('id_estacion');
            $table->primary(['id_sucursal', 'id_categoria']);
            $table->foreign(['id_estacion', 'id_sucursal'], 'estacion_categorias_estacion_fk')
                ->references(['id_estacion', 'id_sucursal'])->on('restaurante.estaciones')->cascadeOnDelete();
        });
    }

    private function crearPedidos(): void
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
            $table->foreign('id_sucursal', 'pedidos_sucursal_fk')->references('id_sucursal')->on('configuracion.sucursales')->restrictOnDelete();
            $table->foreign(['id_mesa', 'id_sucursal'], 'pedidos_mesa_sucursal_fk')->references(['id_mesa', 'id_sucursal'])->on('restaurante.mesas')->restrictOnDelete();
            $table->foreign(['id_pedido_destino', 'id_sucursal'], 'pedidos_destino_sucursal_fk')->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_tipo_check CHECK (tipo IN ('MESA', 'PARA_LLEVAR', 'DELIVERY'))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_estado_check CHECK (estado IN ('ABIERTO', 'CERRADO', 'ANULADO', 'UNIDO'))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_mesa_por_tipo_check CHECK ((tipo = 'MESA') = (id_mesa IS NOT NULL))");
        DB::statement("ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_union_check CHECK ((estado = 'UNIDO') = (id_pedido_destino IS NOT NULL))");
        DB::statement('ALTER TABLE restaurante.pedidos ADD CONSTRAINT pedidos_comensales_check CHECK (comensales IS NULL OR comensales BETWEEN 1 AND 100)');
        // Una sola cuenta abierta por mesa.
        DB::statement("CREATE UNIQUE INDEX pedidos_mesa_abierta_unique ON restaurante.pedidos (id_mesa) WHERE estado = 'ABIERTO' AND id_mesa IS NOT NULL");
        DB::statement("CREATE INDEX pedidos_restaurante_anulados_idx ON restaurante.pedidos (id_sucursal, cerrado_en) WHERE estado = 'ANULADO'");

        Schema::create('restaurante.comandas', function (Blueprint $table): void {
            $table->bigIncrements('id_comanda');
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_pedido');
            // NULL = Cocina general (sucursal sin estaciones configuradas).
            $table->unsignedBigInteger('id_estacion')->nullable();
            $table->unsignedInteger('numero');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('clave_envio', 80);
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->timestampTz('enviada_en')->useCurrent();
            $table->timestampTz('iniciada_en')->nullable();
            $table->timestampTz('lista_en')->nullable();
            $table->timestampTz('entregada_en')->nullable();
            $table->timestampsTz();
            $table->unique(['id_sucursal', 'numero'], 'comandas_sucursal_numero_unique');
            $table->index(['id_sucursal', 'estado', 'id_estacion'], 'comandas_tablero_idx');
            $table->index('id_pedido', 'comandas_pedido_idx');
            $table->index(['id_sucursal', 'enviada_en'], 'comandas_restaurante_fecha_idx');
            $table->foreign(['id_pedido', 'id_sucursal'], 'comandas_pedido_sucursal_fk')->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
            $table->foreign(['id_estacion', 'id_sucursal'], 'comandas_estacion_sucursal_fk')->references(['id_estacion', 'id_sucursal'])->on('restaurante.estaciones')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE restaurante.comandas ADD CONSTRAINT comandas_estado_check CHECK (estado IN ('PENDIENTE', 'EN_PREPARACION', 'LISTA', 'ENTREGADA', 'ANULADA'))");
        // Un mismo envío produce como máximo una comanda por estación (incluida la general).
        DB::statement('CREATE UNIQUE INDEX comandas_envio_estacion_unique ON restaurante.comandas (id_sucursal, clave_envio, COALESCE(id_estacion, 0))');

        Schema::create('restaurante.pedido_detalles', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle_pedido');
            $table->foreignId('id_pedido')->constrained('restaurante.pedidos', 'id_pedido')->restrictOnDelete();
            $table->foreignId('id_precio')->constrained('catalogos.producto_presentaciones', 'id_precio')->restrictOnDelete();
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto')->restrictOnDelete();
            // Copias congeladas al tomar el pedido: la comanda, la precuenta y el historial no cambian
            // si luego se edita la carta (nombre, tipo o precio).
            $table->string('nombre_producto', 200);
            $table->string('tipo_producto', 12)->nullable();
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
            $table->foreignId('id_comanda')->nullable()->constrained('restaurante.comandas', 'id_comanda')->restrictOnDelete();
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
        DB::statement("ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT detalle_pedido_tipo_producto_check CHECK (tipo_producto IN ('DIRECTO', 'PREPARADO'))");
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_cobrado_check CHECK (cantidad_cobrada >= 0 AND cantidad_cobrada <= cantidad)');
        DB::statement('ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_precios_check CHECK (precio_base >= 0 AND precio_modificadores >= 0 AND precio_unitario >= 0 AND subtotal >= 0)');
        DB::statement("ALTER TABLE restaurante.pedido_detalles ADD CONSTRAINT pedido_detalles_anulacion_check CHECK ((estado = 'ANULADO') = (anulado_en IS NOT NULL))");

        Schema::create('restaurante.pedido_detalle_modificadores', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle_modificador');
            $table->foreignId('id_detalle_pedido')->constrained('restaurante.pedido_detalles', 'id_detalle_pedido')->cascadeOnDelete();
            $table->foreignId('id_opcion')->constrained('restaurante.opciones_modificador', 'id_opcion')->restrictOnDelete();
            // Variante elegida cuando la opción es un producto de la carta (descuenta su stock).
            $table->foreignId('id_precio')->nullable()->constrained('catalogos.producto_presentaciones', 'id_precio')->restrictOnDelete();
            // Copias congeladas: el ticket y la comanda no cambian si se edita la carta.
            $table->string('nombre_grupo', 80);
            $table->string('nombre_opcion', 80);
            $table->decimal('precio_adicional', 14, 2)->default(0);
            $table->decimal('cantidad', 10, 3);
            $table->unique(['id_detalle_pedido', 'id_opcion'], 'pedido_detalle_modificadores_unique');
        });
        DB::statement('ALTER TABLE restaurante.pedido_detalle_modificadores ADD CONSTRAINT pedido_detalle_modificadores_cantidad_check CHECK (cantidad > 0)');

        // Historial de solo inserción: apertura, ítems, cambios, transferencias, uniones y anulaciones.
        Schema::create('restaurante.pedido_eventos', function (Blueprint $table): void {
            $table->bigIncrements('id_evento');
            $table->foreignId('id_pedido')->constrained('restaurante.pedidos', 'id_pedido')->restrictOnDelete();
            $table->foreignId('id_detalle_pedido')->nullable()->constrained('restaurante.pedido_detalles', 'id_detalle_pedido')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->jsonb('datos')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->index(['id_pedido', 'creado_en'], 'pedido_eventos_pedido_idx');
        });
    }

    private function crearRecetasYMermas(): void
    {
        // Ingredientes de un plato por sucursal (cantidad en la presentación del insumo).
        Schema::create('restaurante.receta_insumos', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->foreignId('id_precio_plato')->constrained('catalogos.producto_presentaciones', 'id_precio');
            $table->foreignId('id_precio_insumo')->constrained('catalogos.producto_presentaciones', 'id_precio');
            $table->decimal('cantidad', 14, 4);
            $table->primary(['id_sucursal', 'id_precio_plato', 'id_precio_insumo'], 'receta_insumos_pk');
        });
        DB::statement('ALTER TABLE restaurante.receta_insumos ADD CONSTRAINT receta_insumos_cantidad_check CHECK (cantidad > 0 AND id_precio_plato <> id_precio_insumo)');

        // Insumos descontados al enviar un ítem a cocina; se revierten o pasan a merma al anular.
        Schema::create('restaurante.consumos_insumos', function (Blueprint $table): void {
            $table->bigIncrements('id_consumo');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->foreignId('id_detalle_pedido')->constrained('restaurante.pedido_detalles', 'id_detalle_pedido');
            $table->foreignId('id_producto')->constrained('catalogos.productos', 'id_producto');
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->string('estado', 16)->default('CONSUMIDO');
            $table->timestampTz('consumido_en');
            $table->timestampTz('resuelto_en')->nullable();
            $table->unique(['id_detalle_pedido', 'id_producto'], 'consumo_item_insumo_unique');
            $table->index(['id_sucursal', 'consumido_en']);
            $table->index(['id_sucursal', 'resuelto_en']);
        });
        DB::statement("ALTER TABLE restaurante.consumos_insumos ADD CONSTRAINT consumo_insumos_check CHECK (cantidad_base > 0 AND costo_unitario >= 0 AND estado IN ('CONSUMIDO','REVERTIDO','MERMA'))");

        // Bajas de stock sin venta: insumos o productos de venta directa (rotura, vencido, consumo interno).
        Schema::create('restaurante.mermas', function (Blueprint $table): void {
            $table->bigIncrements('id_merma');
            $table->foreignId('id_sucursal')->constrained('configuracion.sucursales', 'id_sucursal');
            $table->foreignId('id_usuario')->constrained('seguridad.usuarios', 'id_usuario');
            $table->foreignId('id_precio')->constrained('catalogos.producto_presentaciones', 'id_precio');
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->string('motivo');
            $table->string('clave', 80);
            $table->string('huella', 64);
            $table->timestampTz('registrada_en');
            $table->unique(['id_sucursal', 'clave']);
            $table->index(['id_sucursal', 'registrada_en']);
        });
        DB::statement('ALTER TABLE restaurante.mermas ADD CONSTRAINT mermas_cantidad_check CHECK (cantidad_base > 0 AND costo_unitario >= 0)');
    }

    public function down(): void
    {
        foreach (['mermas', 'consumos_insumos', 'receta_insumos', 'pedido_eventos', 'pedido_detalle_modificadores', 'pedido_detalles',
            'comandas', 'pedidos', 'estacion_categorias', 'estaciones', 'productos_agotados', 'producto_grupo_modificador',
            'opciones_sucursal', 'opciones_modificador', 'grupos_modificadores', 'precios_sucursal', 'categorias_carta',
            'mesas', 'areas'] as $table) {
            Schema::dropIfExists("restaurante.{$table}");
        }
    }
};
