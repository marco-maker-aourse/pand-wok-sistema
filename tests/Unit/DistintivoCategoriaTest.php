<?php

namespace Tests\Unit;

use App\Support\Restaurante\DistintivoCategoria;
use Tests\TestCase;

/** Platos parecidos de Menú y Combinaciones se distinguen por la etiqueta de su categoría. */
class DistintivoCategoriaTest extends TestCase
{
    public function test_reconoce_las_categorias_configuradas_sin_importar_tildes_ni_mayusculas(): void
    {
        $this->assertSame('Menú', DistintivoCategoria::deCategoria('Menu'));
        $this->assertSame('Menú', DistintivoCategoria::deCategoria('MENÚ'));
        $this->assertSame('Combinación', DistintivoCategoria::deCategoria('Combinaciones'));
        $this->assertNull(DistintivoCategoria::deCategoria('Sopas'));
        $this->assertNull(DistintivoCategoria::deCategoria(null));
    }

    public function test_la_comanda_impresa_lleva_la_etiqueta_delante_del_nombre(): void
    {
        $this->assertSame('Menú: Chaufa c/Lomo', DistintivoCategoria::nombreImpreso('Chaufa c/Lomo', 'Menú'));
        $this->assertSame('Sopa Wantan', DistintivoCategoria::nombreImpreso('Sopa Wantan', null));
    }

    public function test_las_categorias_se_amplian_por_configuracion(): void
    {
        config()->set('restaurante.categorias_distintivas', [...config('restaurante.categorias_distintivas'), 'platos especiales' => 'Especial']);

        $this->assertSame('Especial', DistintivoCategoria::deCategoria('Platos Especiales'));
    }
}
