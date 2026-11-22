<?php

namespace Tests\Feature\Restaurante;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Preparación de una base nueva: sucursales, carta, sopas del menú y series por defecto. */
class PrepararRestauranteCommandTest extends TestCase
{
    use PreparaBaseRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        DB::statement('CREATE TABLE configuracion.series_sucursal (id_sucursal INTEGER PRIMARY KEY, serie_boleta TEXT UNIQUE, ult_nro_boleta INTEGER DEFAULT 0, serie_factura TEXT UNIQUE, ult_nro_factura INTEGER DEFAULT 0, serie_guia TEXT UNIQUE NULL, created_at DATETIME, updated_at DATETIME)');
        // La sucursal 2 ("Norte") ya tiene series propias: no deben cambiar.
        DB::table('configuracion.series_sucursal')->insert(['id_sucursal' => 2, 'serie_boleta' => 'B002', 'serie_factura' => 'F002', 'ult_nro_boleta' => 7]);
    }

    public function test_deja_la_base_lista_y_es_repetible(): void
    {
        $this->artisan('restaurante:preparar')->assertSuccessful();

        $sucursales = DB::table('configuracion.sucursales')->orderBy('id_sucursal')->get()->keyBy('id_sucursal');
        $this->assertSame('CHILCA', $sucursales[1]->nombre, 'La sucursal inicial pasa a ser la primera sede de la carta.');
        $this->assertSame(['chilca', 'tambo', 'tingomaria'], $sucursales->pluck('slug')->filter()->sort()->values()->all());
        $this->assertSame(3, DB::table('restaurante.precios_sucursal')->distinct()->count('id_sucursal'));

        $series = DB::table('configuracion.series_sucursal')->orderBy('id_sucursal')->pluck('serie_boleta', 'id_sucursal');
        $this->assertSame('B001', $series[1]);
        $this->assertSame('B002', $series[2]);
        $this->assertSame(7, (int) DB::table('configuracion.series_sucursal')->where('id_sucursal', 2)->value('ult_nro_boleta'));
        $this->assertCount($sucursales->count(), $series, 'Toda sucursal activa queda con series.');
        $this->assertSame($series->count(), $series->unique()->count(), 'Ninguna serie se repite.');

        $productos = DB::table('catalogos.productos')->count();
        $this->artisan('restaurante:preparar')->assertSuccessful();
        $this->assertSame($productos, DB::table('catalogos.productos')->count(), 'Repetir no duplica la carta.');
        $this->assertEquals($series, DB::table('configuracion.series_sucursal')->orderBy('id_sucursal')->pluck('serie_boleta', 'id_sucursal'));
    }
}
