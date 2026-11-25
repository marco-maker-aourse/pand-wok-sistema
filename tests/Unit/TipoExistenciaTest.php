<?php

namespace Tests\Unit;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\TipoExistencia;
use PHPUnit\Framework\TestCase;

/** Cómo se lleva el stock de cada producto en la pantalla de existencias. */
class TipoExistenciaTest extends TestCase
{
    private function presentacion(?int $idStock, ?string $nombreStock = null): ProductoPresentacion
    {
        $presentacion = new ProductoPresentacion(['id_producto_stock' => $idStock]);
        $presentacion->setRelation('productoStock', $idStock ? new Producto(['nombre' => $nombreStock]) : null);

        return $presentacion;
    }

    private function producto(string $tipo, array $presentaciones): Producto
    {
        $producto = new Producto(['tipo_producto' => $tipo]);
        $producto->setRelation('presentaciones', collect($presentaciones));

        return $producto;
    }

    public function test_insumo_lleva_su_propio_stock(): void
    {
        $datos = TipoExistencia::de($this->producto(Producto::INSUMO, [$this->presentacion(null)]));

        $this->assertSame(['tipo_producto' => 'INSUMO', 'controla_stock' => true, 'stock_en' => []], $datos);
    }

    public function test_venta_directa_con_variantes_fisicas_lleva_el_stock_en_ellas(): void
    {
        $datos = TipoExistencia::de($this->producto(Producto::DIRECTO, [
            $this->presentacion(41, 'Inca Kola botella 500 ml'),
            $this->presentacion(43, 'Inca Kola botella 1 L'),
            $this->presentacion(41, 'Inca Kola botella 500 ml'),
        ]));

        $this->assertFalse($datos['controla_stock']);
        $this->assertSame(['Inca Kola botella 500 ml', 'Inca Kola botella 1 L'], $datos['stock_en']);
    }

    public function test_venta_directa_sin_variantes_controla_su_stock(): void
    {
        $this->assertTrue(TipoExistencia::de($this->producto(Producto::DIRECTO, [$this->presentacion(null)]))['controla_stock']);
    }

    public function test_preparado_no_lleva_stock_propio(): void
    {
        $datos = TipoExistencia::de($this->producto(Producto::PREPARADO, [$this->presentacion(null)]));

        $this->assertFalse($datos['controla_stock']);
        $this->assertSame([], $datos['stock_en']);
    }

    public function test_sin_tipo_se_trata_como_venta_directa(): void
    {
        $datos = TipoExistencia::de($this->producto('', [$this->presentacion(null)]));

        $this->assertSame('DIRECTO', $datos['tipo_producto']);
        $this->assertTrue($datos['controla_stock']);
    }

    public function test_la_condicion_sql_excluye_preparados_y_variantes_fisicas(): void
    {
        $sql = TipoExistencia::condicionSql('producto');

        $this->assertStringContainsString("producto.tipo_producto <> 'PREPARADO'", $sql);
        $this->assertStringContainsString('pp.id_producto_stock IS NULL', $sql);
        $this->assertStringContainsString('producto.es_servicio = FALSE', $sql);
    }

    public function test_la_condicion_sql_rechaza_alias_no_validos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TipoExistencia::condicionSql('producto; DROP TABLE x');
    }
}
