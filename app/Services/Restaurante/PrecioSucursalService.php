<?php

namespace App\Services\Restaurante;

use App\Models\ProductoPresentacion;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Precio vigente de una presentación en una sucursal.
 *
 * Regla única para todo el restaurante (toma de pedidos, carta y carta pública):
 * - Con fila en restaurante.precios_sucursal y disponible = true → ese precio.
 * - Con fila y disponible = false → no se vende en esa sucursal.
 * - Sin fila → precio general de catalogos.producto_presentaciones.
 */
class PrecioSucursalService
{
    public function precio(ProductoPresentacion $precio, int $sucursalId): float
    {
        $propio = DB::table('restaurante.precios_sucursal')
            ->where('id_sucursal', $sucursalId)
            ->where('id_precio', $precio->id_precio)
            ->first(['precio_venta', 'disponible']);

        if ($propio && ! $propio->disponible) {
            throw new DomainException("{$precio->producto?->nombre} no se vende en esta sucursal.");
        }

        return round((float) ($propio->precio_venta ?? $precio->precio_venta), 2);
    }

    /**
     * Ajustes de varias presentaciones en una sucursal, en una sola consulta.
     *
     * @param  array<int>  $idsPrecio
     * @return Collection<int, object{precio_venta: ?string, disponible: bool}>
     */
    public function ajustes(array $idsPrecio, int $sucursalId): Collection
    {
        if ($idsPrecio === []) {
            return collect();
        }

        return DB::table('restaurante.precios_sucursal')
            ->where('id_sucursal', $sucursalId)
            ->whereIn('id_precio', $idsPrecio)
            ->get(['id_precio', 'precio_venta', 'disponible'])
            ->keyBy(fn ($fila) => (int) $fila->id_precio);
    }

    /**
     * Fija el precio propio de la sucursal, la marca como no vendida o vuelve al general.
     * $precioVenta null + $disponible true = volver al precio general (borra el ajuste).
     */
    public function fijar(int $idPrecio, int $sucursalId, ?float $precioVenta, bool $disponible): void
    {
        ProductoPresentacion::query()->findOrFail($idPrecio);

        if ($precioVenta !== null && ($precioVenta < 0 || $precioVenta > 999999)) {
            throw new DomainException('El precio debe estar entre 0 y 999 999.');
        }

        $clave = ['id_sucursal' => $sucursalId, 'id_precio' => $idPrecio];

        if ($disponible && $precioVenta === null) {
            DB::table('restaurante.precios_sucursal')->where($clave)->delete();

            return;
        }

        DB::table('restaurante.precios_sucursal')->updateOrInsert($clave, [
            'precio_venta' => $disponible ? round($precioVenta, 2) : null,
            'disponible' => $disponible,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
}
