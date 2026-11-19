<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Series y correlativos por sucursal.
 *
 * datos_gen guarda la empresa (RUC único) y las series de la sucursal principal. Las demás
 * sucursales comparten esos datos fiscales pero SUNAT exige que cada establecimiento emita
 * con su propia serie (B002, F002…); sin esta tabla no podían emitir boletas ni facturas.
 * Las series son únicas en todo el sistema: dos sedes nunca comparten numeración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones.series_sucursal', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')->primary()->constrained('inventario.sucursales', 'id_sucursal')->cascadeOnDelete();
            $table->string('serie_boleta', 4)->nullable()->unique();
            $table->unsignedInteger('ult_nro_boleta')->default(0);
            $table->string('serie_factura', 4)->nullable()->unique();
            $table->unsignedInteger('ult_nro_factura')->default(0);
            $table->string('serie_guia', 4)->nullable()->unique();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones.series_sucursal');
    }
};
