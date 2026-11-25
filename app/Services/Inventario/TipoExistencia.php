<?php

namespace App\Services\Inventario;

use App\Models\Producto;
use App\Models\ProductoPresentacion;

/**
 * Cómo se lleva el stock de un producto, para que la pantalla de existencias no confunda
 * una presentación de venta con una variante física de inventario:
 *
 * - INSUMO: tiene saldo propio.
 * - DIRECTO: tiene saldo propio, salvo que sus presentaciones descuenten de otros productos
 *   (p. ej. «Inca Kola 500 ml» descuenta de «Inca Kola botella 500 ml»).
 * - PREPARADO: no lleva stock; consume insumos por receta.
 *
 * Requiere las presentaciones cargadas con `productoStock`.
 */
final class TipoExistencia
{
    /** @return array{tipo_producto: string, controla_stock: bool, stock_en: list<string>} */
    public static function de(Producto $producto): array
    {
        $tipo = in_array($producto->tipo_producto, Producto::TIPOS, true) ? $producto->tipo_producto : Producto::DIRECTO;
        $presentaciones = $producto->presentaciones;

        $stockEn = $presentaciones
            ->filter(fn (ProductoPresentacion $p): bool => $p->id_producto_stock !== null && $p->productoStock !== null)
            ->map(fn (ProductoPresentacion $p): string => (string) $p->productoStock->nombre)
            ->unique()
            ->values()
            ->all();

        $todasDescuentanDeOtro = $presentaciones->isNotEmpty()
            && $presentaciones->every(fn (ProductoPresentacion $p): bool => $p->id_producto_stock !== null);

        return [
            'tipo_producto' => $tipo,
            'controla_stock' => $tipo !== Producto::PREPARADO && ! $todasDescuentanDeOtro,
            'stock_en' => $tipo === Producto::PREPARADO ? [] : $stockEn,
        ];
    }
}
