<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurante — cocina: estaciones de preparación, comandas e impresión de comandas.
 *
 * - estaciones: cocina caliente, fría, bar… por sucursal. Cada una puede imprimir en una
 *   impresora a través del agente de una terminal (ambos o ninguno).
 * - estacion_categorias: a qué estación va cada categoría en esa sucursal. Lo que no tenga
 *   estación va a la estación por defecto o, si no hay, a "Cocina general" (id_estacion NULL).
 * - comandas: cada envío a cocina genera una comanda por estación. clave_envio hace
 *   idempotente el envío: el mismo envío repetido no duplica comandas ni impresiones.
 * - trabajos_impresion acepta el tipo COMANDA y se enlaza a la comanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante.estaciones', function (Blueprint $table): void {
            $table->bigIncrements('id_estacion');
            $table->foreignId('id_sucursal')->constrained('inventario.sucursales', 'id_sucursal')->restrictOnDelete();
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
        DB::statement('ALTER TABLE restaurante.estaciones ADD CONSTRAINT estaciones_impresion_check CHECK ((id_impresora IS NULL) = (id_terminal IS NULL))');
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
            $table->foreign(['id_pedido', 'id_sucursal'], 'comandas_pedido_sucursal_fk')
                ->references(['id_pedido', 'id_sucursal'])->on('restaurante.pedidos')->restrictOnDelete();
            $table->foreign(['id_estacion', 'id_sucursal'], 'comandas_estacion_sucursal_fk')
                ->references(['id_estacion', 'id_sucursal'])->on('restaurante.estaciones')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE restaurante.comandas ADD CONSTRAINT comandas_estado_check CHECK (estado IN ('PENDIENTE', 'EN_PREPARACION', 'LISTA', 'ENTREGADA', 'ANULADA'))");
        // Un mismo envío produce como máximo una comanda por estación (incluida la general).
        DB::statement('CREATE UNIQUE INDEX comandas_envio_estacion_unique ON restaurante.comandas (clave_envio, COALESCE(id_estacion, 0))');

        Schema::table('restaurante.pedido_detalles', function (Blueprint $table): void {
            $table->foreign('id_comanda', 'pedido_detalles_comanda_fk')
                ->references('id_comanda')->on('restaurante.comandas')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE operaciones.trabajos_impresion DROP CONSTRAINT trabajos_impresion_tipo_check');
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_tipo_check CHECK (tipo IN ('TICKET_VENTA', 'PRUEBA', 'DIAGNOSTICO', 'COMANDA'))");
        Schema::table('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_comanda')->nullable();
            $table->index('id_comanda', 'trabajos_impresion_comanda_idx');
            $table->foreign('id_comanda', 'trabajos_impresion_comanda_fk')
                ->references('id_comanda')->on('restaurante.comandas')->restrictOnDelete();
        });

        $this->registrarPermisos();
    }

    public function down(): void
    {
        DB::table('seguridad.permisos')->whereIn('codigo', array_column(self::PERMISOS, 'codigo'))->delete();

        Schema::table('operaciones.trabajos_impresion', function (Blueprint $table): void {
            $table->dropForeign('trabajos_impresion_comanda_fk');
            $table->dropIndex('trabajos_impresion_comanda_idx');
            $table->dropColumn('id_comanda');
        });
        DB::statement("DELETE FROM operaciones.trabajos_impresion WHERE tipo = 'COMANDA'");
        DB::statement('ALTER TABLE operaciones.trabajos_impresion DROP CONSTRAINT trabajos_impresion_tipo_check');
        DB::statement("ALTER TABLE operaciones.trabajos_impresion ADD CONSTRAINT trabajos_impresion_tipo_check CHECK (tipo IN ('TICKET_VENTA', 'PRUEBA', 'DIAGNOSTICO'))");

        Schema::table('restaurante.pedido_detalles', function (Blueprint $table): void {
            $table->dropForeign('pedido_detalles_comanda_fk');
        });
        Schema::dropIfExists('restaurante.comandas');
        Schema::dropIfExists('restaurante.estacion_categorias');
        Schema::dropIfExists('restaurante.estaciones');
    }

    private const PERMISOS = [
        ['codigo' => 'cocina.ver', 'nombre' => 'Ver pantalla de cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'cocina.gestionar', 'nombre' => 'Cambiar estado de comandas', 'modulo' => 'restaurante'],
        ['codigo' => 'cocina.configurar', 'nombre' => 'Configurar estaciones de cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'comandas.enviar', 'nombre' => 'Enviar pedidos a cocina', 'modulo' => 'restaurante'],
    ];

    /** Habilita los permisos en instalaciones existentes sin volver a ejecutar el seeder. */
    private function registrarPermisos(): void
    {
        foreach (self::PERMISOS as $permiso) {
            DB::table('seguridad.permisos')->insertOrIgnore([...$permiso, 'estado' => true]);
        }

        DB::table('seguridad.roles')->insertOrIgnore([
            'nombre' => 'Cocina',
            'descripcion' => 'Ve las comandas de cocina y actualiza su preparación.',
            'estado' => true,
        ]);

        $permisosPorRol = [
            'Administrador' => array_column(self::PERMISOS, 'codigo'),
            'Mozo' => ['comandas.enviar'],
            'Cocina' => ['cocina.ver', 'cocina.gestionar'],
        ];
        $roles = DB::table('seguridad.roles')->whereIn('nombre', array_keys($permisosPorRol))->pluck('id_rol', 'nombre');
        $permisos = DB::table('seguridad.permisos')->whereIn('codigo', array_column(self::PERMISOS, 'codigo'))->pluck('id_permiso', 'codigo');
        foreach ($roles as $nombre => $idRol) {
            DB::table('seguridad.rol_permisos')->insertOrIgnore(array_map(
                fn (string $codigo): array => ['id_rol' => $idRol, 'id_permiso' => $permisos[$codigo]],
                $permisosPorRol[$nombre],
            ));
        }
    }
};
