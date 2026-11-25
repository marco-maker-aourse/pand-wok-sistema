<?php

namespace Tests\Feature\Restaurante;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alta rápida de empaques desde el formulario de producto (petición JSON). Si el nombre ya
 * existe, se devuelve el existente en lugar de fallar: el empaque pudo crearse en un intento
 * anterior o estar desactivado, y la pantalla solo necesita seleccionarlo.
 */
class PresentacionRapidaTest extends TestCase
{
    use PreparaOperacionRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
        $this->actingAs(User::query()->findOrFail(10));
    }

    private function crear(string $nombre)
    {
        return $this->postJson('/presentaciones', ['nombre' => $nombre, 'estado' => true]);
    }

    public function test_crea_un_empaque_nuevo(): void
    {
        $this->crear('TAZA')->assertCreated()->assertJsonPath('data.nombre', 'TAZA');
        $this->assertSame(1, DB::table('catalogos.presentaciones')->where('nombre', 'TAZA')->count());
    }

    public function test_repetir_el_nombre_devuelve_el_existente_sin_duplicar(): void
    {
        $id = $this->crear('TAZA')->json('data.id_presentacion');

        $this->crear('taza ')->assertOk()->assertJsonPath('data.id_presentacion', $id);
        $this->assertSame(1, DB::table('catalogos.presentaciones')->whereRaw('UPPER(nombre) = ?', ['TAZA'])->count());
    }

    public function test_un_empaque_desactivado_se_reactiva(): void
    {
        $id = DB::table('catalogos.presentaciones')->insertGetId(['nombre' => 'TAZA', 'estado' => false]);

        $this->crear('Taza')->assertOk()->assertJsonPath('data.id_presentacion', $id)->assertJsonPath('data.estado', true);
        $this->assertTrue((bool) DB::table('catalogos.presentaciones')->where('id_presentacion', $id)->value('estado'));
    }

    public function test_el_formulario_de_la_pagina_sigue_rechazando_duplicados(): void
    {
        DB::table('catalogos.presentaciones')->insert(['nombre' => 'TAZA', 'estado' => true]);

        $this->post('/presentaciones', ['nombre' => 'TAZA'])->assertSessionHasErrors('nombre');
    }
}
