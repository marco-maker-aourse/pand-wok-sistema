<?php

namespace Tests\Unit;

use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use PHPUnit\Framework\TestCase;

class VentaPorPresentacionTest extends TestCase
{
    public function test_convierte_la_presentacion_mayorista_a_unidades_base(): void
    {
        $presentacion = new ProductoPresentacion([
            'factor_conversion' => 12,
            'precio_venta' => 48.00,
        ]);

        $cantidadBase = (new GestionarExistenciaService())
            ->toBaseQuantity($presentacion, 2);

        $this->assertSame(24.0, $cantidadBase);
    }
}
