<?php

namespace Tests\Unit;

use App\Services\Inventario\KardexValorizadoService;
use PHPUnit\Framework\TestCase;

/** Kardex valorizado por costo promedio ponderado, con datos ficticios y sin base de datos. */
class KardexValorizadoTest extends TestCase
{
    private static function fila(int $id, float $entrada, float $salida, float $stock, float $costo, ?string $tipo = null, ?string $documento = null): array
    {
        return [
            'id' => $id, 'fecha' => '05/11/2026 10:00', 'entrada' => $entrada, 'salida' => $salida,
            'stock' => $stock, 'costo' => $costo, 'tipo' => $tipo, 'documento' => $documento,
            'id_referencia' => null, 'usuario' => null,
        ];
    }

    public function test_valoriza_entradas_salidas_y_saldos_con_costo_promedio(): void
    {
        // Saldo inicial: 10 u. a S/ 2.00 = S/ 20.00.
        // Compra 5 u. a S/ 3.20 → promedio (20 + 16) / 15 = 2.40. Venta 4 u. y ajuste de −1 u. a 2.40.
        $kardex = KardexValorizadoService::armar(self::fila(1, 10, 0, 10, 2), [
            self::fila(2, 5, 0, 15, 2.4, 'INGRESO', 'F001-0045'),
            self::fila(3, 0, 4, 11, 2.4, 'VENTA', 'B001-00000015'),
            self::fila(4, 0, 1, 10, 2.4, 'AJUSTE', 'Producto dañado'),
        ]);

        $this->assertSame(['cantidad' => 10.0, 'costo_unitario' => 2.0, 'total' => 20.0], $kardex['saldo_inicial']);

        [$compra, $venta, $ajuste] = $kardex['movimientos'];
        $this->assertSame(['cantidad' => 5.0, 'costo_unitario' => 3.2, 'total' => 16.0], $compra['entrada']);
        $this->assertNull($compra['salida']);
        $this->assertSame(['cantidad' => 15.0, 'costo_unitario' => 2.4, 'total' => 36.0], $compra['saldo']);
        $this->assertSame('Compra / ingreso', $compra['operacion']);

        $this->assertSame(['cantidad' => 4.0, 'costo_unitario' => 2.4, 'total' => 9.6], $venta['salida']);
        $this->assertSame('B001-00000015', $venta['documento']);
        $this->assertSame(['cantidad' => 1.0, 'costo_unitario' => 2.4, 'total' => 2.4], $ajuste['salida']);
        $this->assertSame('Ajuste de inventario', $ajuste['operacion']);

        $this->assertSame(['entradas_cantidad' => 5.0, 'entradas_total' => 16.0, 'salidas_cantidad' => 5.0, 'salidas_total' => 12.0], $kardex['totales']);
        // Cuadre: inicial + entradas − salidas = final.
        $this->assertSame(['cantidad' => 10.0, 'costo_unitario' => 2.4, 'total' => 24.0], $kardex['saldo_final']);
        $this->assertEqualsWithDelta(20 + 16 - 12, $kardex['saldo_final']['total'], 0.001);
    }

    public function test_sin_movimientos_previos_parte_de_cero(): void
    {
        $kardex = KardexValorizadoService::armar(null, [self::fila(1, 12, 0, 12, 1.5)]);

        $this->assertSame(['cantidad' => 0.0, 'costo_unitario' => 0.0, 'total' => 0.0], $kardex['saldo_inicial']);
        $this->assertSame(['cantidad' => 12.0, 'costo_unitario' => 1.5, 'total' => 18.0], $kardex['movimientos'][0]['entrada']);
        $this->assertSame(18.0, $kardex['saldo_final']['total']);
    }

    public function test_movimientos_anteriores_a_la_migracion_se_muestran_como_entrada_o_salida(): void
    {
        $kardex = KardexValorizadoService::armar(self::fila(1, 3, 0, 3, 1), [
            self::fila(2, 2, 0, 5, 1),
            self::fila(3, 0, 1, 4, 1),
        ]);

        $this->assertSame(['ENTRADA', 'SALIDA'], array_column($kardex['movimientos'], 'tipo'));
        $this->assertSame(['Entrada', 'Salida'], array_column($kardex['movimientos'], 'operacion'));
        $this->assertSame([null, null], array_column($kardex['movimientos'], 'documento'));
    }

    public function test_periodo_sin_movimientos_mantiene_el_saldo(): void
    {
        $kardex = KardexValorizadoService::armar(self::fila(9, 0, 2, 8, 3.25), []);

        $this->assertSame([], $kardex['movimientos']);
        $this->assertSame($kardex['saldo_inicial'], $kardex['saldo_final']);
        $this->assertSame(26.0, $kardex['saldo_final']['total']);
    }

    public function test_cantidades_fraccionadas_conservan_cuatro_decimales(): void
    {
        $kardex = KardexValorizadoService::armar(null, [
            self::fila(1, 2.5, 0, 2.5, 4.8),
            self::fila(2, 0, 0.125, 2.375, 4.8),
        ]);

        $this->assertSame(0.125, $kardex['movimientos'][1]['salida']['cantidad']);
        $this->assertSame(0.6, $kardex['movimientos'][1]['salida']['total']);
        $this->assertSame(['cantidad' => 2.375, 'costo_unitario' => 4.8, 'total' => 11.4], $kardex['saldo_final']);
    }
}
