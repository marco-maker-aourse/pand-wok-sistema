<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquemas del sistema y catálogos de referencia (la mayoría son códigos SUNAT).
 *
 * - configuracion: empresa, sucursales y series de comprobantes.
 * - seguridad: usuarios, roles y permisos.
 * - catalogos: productos y sus presentaciones, clientes, proveedores, impresoras.
 * - restaurante: salón, carta, pedidos, cocina, recetas y mermas.
 * - inventario: movimientos de existencias (kardex).
 * - operaciones: caja, compras, ventas, pagos y cola de impresión.
 */
return new class extends Migration {
    public $withinTransaction = false;

    private const ESQUEMAS = ['configuracion', 'seguridad', 'catalogos', 'restaurante', 'inventario', 'operaciones'];

    public function up(): void
    {
        foreach (self::ESQUEMAS as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }

        Schema::create('catalogos.tipos_documento', function (Blueprint $table): void {
            $table->bigIncrements('id_tipo_documento');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('longitud_exacta')->nullable();
            $table->string('tipo_persona', 20)->nullable();
            $table->boolean('estado')->default(true);
        });

        Schema::create('catalogos.tipos_comprobante', function (Blueprint $table): void {
            $table->bigIncrements('id_tipo_comprobante');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('descripcion', 150);
            $table->boolean('exige_ruc')->default(false);
            $table->boolean('permite_anulacion')->default(true);
            $table->boolean('estado')->default(true);
        });

        Schema::create('catalogos.unidades_medida', function (Blueprint $table): void {
            $table->bigIncrements('id_unidad');
            $table->string('codigo_sunat', 5)->unique();
            $table->string('nombre', 100);
            $table->boolean('estado')->default(true);
        });

        Schema::create('catalogos.categorias', function (Blueprint $table): void {
            $table->bigIncrements('id_categoria');
            $table->string('nombre', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
        });

        Schema::create('catalogos.marcas', function (Blueprint $table): void {
            $table->bigIncrements('id_marca');
            $table->string('nombre', 100)->unique();
            $table->boolean('estado')->default(true);
        });

        // Tamaños o empaques de venta: "300 ml", "1 L", "Personal", "Familiar", "Caja x12".
        Schema::create('catalogos.presentaciones', function (Blueprint $table): void {
            $table->bigIncrements('id_presentacion');
            $table->string('nombre', 100)->unique();
            $table->boolean('estado')->default(true);
        });

        Schema::create('catalogos.metodos_pago', function (Blueprint $table): void {
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
        foreach (array_reverse(self::ESQUEMAS) as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
};
