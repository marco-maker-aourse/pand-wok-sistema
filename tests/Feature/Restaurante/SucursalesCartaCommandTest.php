<?php

namespace Tests\Feature\Restaurante;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SucursalesCartaCommandTest extends TestCase
{
    use PreparaBaseRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
    }

    private function slugs(): array
    {
        return DB::table('inventario.sucursales')->orderBy('id_sucursal')->pluck('slug', 'id_sucursal')->all();
    }

    public function test_lista_sin_cambiar_nada(): void
    {
        $this->artisan('restaurante:sucursales')->expectsOutputToContain('Centro')->assertSuccessful();
        $this->assertSame([1 => null, 2 => null], $this->slugs());
    }

    public function test_asigna_identificadores_y_horarios_desde_la_carta(): void
    {
        $this->artisan('restaurante:sucursales', [
            '--asignar' => ['chilca=1', 'tambo=2'],
            '--horarios' => 'database/data/carta/pand-wok.json',
        ])->assertSuccessful();

        $this->assertSame([1 => 'chilca', 2 => 'tambo'], $this->slugs());
        $this->assertSame('10:30 AM - 11:00 PM', DB::table('inventario.sucursales')->where('id_sucursal', 1)->value('horario_atencion'));
    }

    public function test_no_pisa_un_horario_existente_y_permite_intercambiar(): void
    {
        DB::table('inventario.sucursales')->where('id_sucursal', 1)->update(['slug' => 'tambo', 'horario_atencion' => '9 a 5']);

        $this->artisan('restaurante:sucursales', [
            '--asignar' => ['chilca=1', 'tambo=2'],
            '--horarios' => 'database/data/carta/pand-wok.json',
        ])->assertSuccessful();

        $this->assertSame([1 => 'chilca', 2 => 'tambo'], $this->slugs());
        $this->assertSame('9 a 5', DB::table('inventario.sucursales')->where('id_sucursal', 1)->value('horario_atencion'));
    }

    public function test_rechaza_datos_invalidos_sin_escribir(): void
    {
        DB::table('inventario.sucursales')->where('id_sucursal', 1)->update(['slug' => 'chilca']);

        $casos = [
            ['Chilca=1'],             // mayúsculas
            ['chilca=1;rm'],          // formato
            ['tambo=9'],              // sucursal inexistente
            ['chilca=2'],             // identificador de otra sucursal que no se reasigna
            ['a=1', 'b=1'],           // dos identificadores para la misma sucursal
        ];
        foreach ($casos as $caso) {
            $this->artisan('restaurante:sucursales', ['--asignar' => $caso])->assertFailed();
        }

        $this->assertSame([1 => 'chilca', 2 => null], $this->slugs());
    }

    public function test_tras_asignar_la_importacion_asocia_las_sedes_sola(): void
    {
        $this->artisan('restaurante:sucursales', ['--asignar' => ['chilca=1', 'tambo=2']])->assertSuccessful();

        $this->artisan('restaurante:importar-carta', ['archivo' => 'database/data/carta/pand-wok.json'])
            ->expectsOutputToContain('Carta importada.')
            ->assertSuccessful();

        $this->assertSame(176 + 3, DB::table('catalogos.productos')->count());
        $this->assertSame(2, DB::table('restaurante.precios_sucursal')->distinct()->count('id_sucursal'));
    }
}
