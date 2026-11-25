<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de la emisión SUNAT y nota de venta sin valor tributario.
 *
 * - operaciones.ventas: el motivo exacto del último intento (mensaje y código del CDR), cuántas
 *   veces se intentó y cuándo. Antes solo quedaba el estado y el motivo se perdía en el log.
 * - Nota de venta (NV): comprobante interno para ventas sin identificar al cliente. No se envía a
 *   SUNAT (estado_sunat = NO_APLICA) y numera con su propia serie por sucursal
 *   (configuracion.series_sucursal; sin serie configurada usa NV + número de sucursal).
 *
 * Solo agrega columnas con valor por defecto y un tipo de comprobante; reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones.ventas', function (Blueprint $table): void {
            $table->text('mensaje_sunat')->nullable();
            $table->string('codigo_respuesta_sunat', 10)->nullable();
            $table->unsignedSmallInteger('intentos_sunat')->default(0);
            $table->timestampTz('ultimo_intento_sunat')->nullable();
        });

        Schema::table('configuracion.series_sucursal', function (Blueprint $table): void {
            $table->string('serie_nota_venta', 4)->nullable()->unique();
            $table->unsignedInteger('ult_nro_nota_venta')->default(0);
        });

        if (! DB::table('catalogos.tipos_comprobante')->where('codigo_sunat', 'NV')->exists()) {
            DB::table('catalogos.tipos_comprobante')->insert([
                'codigo_sunat' => 'NV', 'descripcion' => 'Nota de venta (sin valor tributario)',
                'exige_ruc' => false, 'permite_anulacion' => true, 'estado' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('operaciones.ventas as v')->join('catalogos.tipos_comprobante as t', 't.id_tipo_comprobante', '=', 'v.id_tipo_comprobante')
            ->where('t.codigo_sunat', 'NV')->exists()) {
            throw new RuntimeException('Hay notas de venta registradas que deben preservarse.');
        }
        DB::table('catalogos.tipos_comprobante')->where('codigo_sunat', 'NV')->delete();

        // El índice vive en el esquema configuracion: dropUnique() lo buscaría en public.
        DB::statement('DROP INDEX IF EXISTS configuracion.configuracion_series_sucursal_serie_nota_venta_unique');
        Schema::table('configuracion.series_sucursal', function (Blueprint $table): void {
            $table->dropColumn(['serie_nota_venta', 'ult_nro_nota_venta']);
        });
        Schema::table('operaciones.ventas', function (Blueprint $table): void {
            $table->dropColumn(['mensaje_sunat', 'codigo_respuesta_sunat', 'intentos_sunat', 'ultimo_intento_sunat']);
        });
    }
};
