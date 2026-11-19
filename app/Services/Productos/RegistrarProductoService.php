<?php

namespace App\Services\Productos;

use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un producto con su precio al detalle y presentaciones mayoristas.
 *
 * Producto y precios se confirman juntos: un artículo nunca queda creado sin
 * las presentaciones que el usuario configuró en el asistente.
 */
class RegistrarProductoService
{
    public function execute(array $datos): Producto
    {
        return DB::transaction(function () use ($datos): Producto {
            $producto = Producto::create([
                'nombre'          => $datos['nombre'],
                'id_categoria'    => $datos['id_categoria'],
                'id_marca'        => $datos['id_marca'] ?? null,
                'id_unidad_base'  => $datos['id_unidad_base'] ?? null,
                'codigo_barras'   => $datos['codigo_barras'] ?? null,
                'codigo_sunat'    => $datos['codigo_sunat'] ?? null,
                'estado'          => true,
                'afecta_igv'      => $datos['afecta_igv'],
                'es_servicio'     => $datos['es_servicio'],
                'usa_vencimiento' => $datos['usa_vencimiento'],
            ]);

            if (filled($datos['precio_venta_unitario'] ?? null)) {
                $this->registrarPrecioAlDetalle($producto, (float) $datos['precio_venta_unitario'], (int) ($datos['id_presentacion_unitaria'] ?? 0));
            }

            foreach ($datos['presentaciones_adicionales'] ?? [] as $presentacion) {
                if (is_array($presentacion)) {
                    $this->registrarPresentacionMayorista($producto, $presentacion);
                }
            }

            return $producto;
        });
    }

    /** Venta suelta (factor 1). Sin presentación elegida se usa la presentación UNIDAD. */
    private function registrarPrecioAlDetalle(Producto $producto, float $precio, int $presentacionId): void
    {
        $presentacionId = $presentacionId ?: Presentacion::firstOrCreate(['nombre' => 'UNIDAD'], ['estado' => true])->id_presentacion;

        ProductoPresentacion::updateOrCreate(
            ['id_producto' => $producto->id_producto, 'id_presentacion' => $presentacionId],
            ['factor_conversion' => 1, 'precio_venta' => $precio, 'estado' => true],
        );
    }

    /** Solo se registran filas completas: presentación elegida y precio positivo. */
    private function registrarPresentacionMayorista(Producto $producto, array $presentacion): void
    {
        $precio = isset($presentacion['precio_venta']) ? (float) $presentacion['precio_venta'] : 0.0;

        if (empty($presentacion['id_presentacion']) || $precio <= 0) {
            return;
        }

        ProductoPresentacion::updateOrCreate(
            ['id_producto' => $producto->id_producto, 'id_presentacion' => $presentacion['id_presentacion']],
            [
                'factor_conversion' => isset($presentacion['factor_conversion']) ? (float) $presentacion['factor_conversion'] : 1.0,
                'precio_venta'      => $precio,
                'estado'            => true,
            ],
        );
    }
}
