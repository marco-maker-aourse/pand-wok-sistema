<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\GrupoModificador;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Convierte lo que pide el mozo (presentación, cantidad, opciones) en un ítem con
 * precio calculado en el servidor. El navegador nunca envía precios.
 *
 * Reglas:
 * - La presentación y el producto deben estar activos; los insumos no se venden.
 * - El producto no debe estar agotado en la sucursal.
 * - Cada opción debe estar activa y pertenecer a un grupo asignado al producto.
 * - Se respeta el mínimo y máximo de selección de cada grupo (obligatorios incluidos).
 */
class ArmarItemPedidoService
{
    public const CANTIDAD_MAXIMA = 999;

    /**
     * @param  array{id_precio:int, cantidad:float|int|string, observacion?:?string, opciones?:array<int>}  $item
     * @return array{detalle: array<string, mixed>, modificadores: array<int, array<string, mixed>>}
     */
    public function armar(array $item, int $sucursalId): array
    {
        $cantidad = round((float) ($item['cantidad'] ?? 0), 3);
        if ($cantidad <= 0 || $cantidad > self::CANTIDAD_MAXIMA) {
            throw new DomainException('La cantidad debe ser mayor que cero y no superar '.self::CANTIDAD_MAXIMA.'.');
        }

        $precio = ProductoPresentacion::query()
            ->with('producto')
            ->where('estado', true)
            ->find((int) ($item['id_precio'] ?? 0));

        $producto = $precio?->producto;
        if (! $precio || ! $producto || ! $producto->estado) {
            throw new DomainException('El producto seleccionado no está disponible en la carta.');
        }
        if ($producto->tipo_producto === Producto::INSUMO) {
            throw new DomainException("{$producto->nombre} es un insumo y no se vende en la carta.");
        }

        $agotado = DB::table('restaurante.productos_agotados')
            ->where('id_sucursal', $sucursalId)
            ->where('id_producto', $producto->id_producto)
            ->exists();
        if ($agotado) {
            throw new DomainException("{$producto->nombre} está agotado en esta sucursal.");
        }

        $modificadores = $this->modificadores($producto, $item['opciones'] ?? []);
        $precioBase = round((float) $precio->precio_venta, 4);
        $precioModificadores = round(array_sum(array_column($modificadores, 'precio_adicional')), 4);
        $precioUnitario = round($precioBase + $precioModificadores, 4);

        return [
            'detalle' => [
                'id_precio' => $precio->id_precio,
                'id_producto' => $producto->id_producto,
                'nombre_producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_base' => $precioBase,
                'precio_modificadores' => $precioModificadores,
                'precio_unitario' => $precioUnitario,
                'subtotal' => self::subtotal($cantidad, $precioUnitario),
                'observacion' => self::textoOpcional($item['observacion'] ?? null),
            ],
            'modificadores' => $modificadores,
        ];
    }

    public static function subtotal(float $cantidad, float $precioUnitario): float
    {
        return round($cantidad * $precioUnitario, 2);
    }

    public static function textoOpcional(?string $texto): ?string
    {
        $limpio = trim((string) $texto);

        return $limpio === '' ? null : mb_substr($limpio, 0, 255);
    }

    /** @return array<int, array{id_opcion:int, nombre_grupo:string, nombre_opcion:string, precio_adicional:float}> */
    private function modificadores(Producto $producto, array $opcionesElegidas): array
    {
        $ids = array_map('intval', $opcionesElegidas);
        if (count($ids) !== count(array_unique($ids))) {
            throw new DomainException('Una opción se eligió más de una vez.');
        }

        // Dos consultas simples en vez de la relación muchos-a-muchos: así no dependen
        // de nombres de tres partes (esquema.tabla.*), que SQLite no admite.
        $orden = DB::table('restaurante.producto_grupo_modificador')
            ->where('id_producto', $producto->id_producto)
            ->orderBy('orden')
            ->pluck('id_grupo')
            ->map(fn ($id) => (int) $id);

        $grupos = GrupoModificador::query()
            ->whereKey($orden->all())
            ->where('activo', true)
            ->with(['opciones' => fn ($query) => $query->where('activo', true)])
            ->get()
            ->sortBy(fn (GrupoModificador $grupo) => $orden->search((int) $grupo->id_grupo))
            ->values();

        $opcionesValidas = $grupos->flatMap(fn (GrupoModificador $grupo) => $grupo->opciones->map(
            fn ($opcion) => ['opcion' => $opcion, 'grupo' => $grupo]
        ))->keyBy(fn (array $par) => $par['opcion']->id_opcion);

        foreach ($ids as $id) {
            if (! $opcionesValidas->has($id)) {
                throw new DomainException("Una de las opciones elegidas no corresponde a {$producto->nombre}.");
            }
        }

        foreach ($grupos as $grupo) {
            $elegidas = collect($ids)->filter(fn (int $id) => (int) $opcionesValidas[$id]['grupo']->id_grupo === (int) $grupo->id_grupo)->count();
            if ($elegidas < $grupo->seleccion_minima) {
                throw new DomainException("Elige al menos {$grupo->seleccion_minima} opción(es) en «{$grupo->nombre}».");
            }
            if ($elegidas > $grupo->seleccion_maxima) {
                throw new DomainException("Puedes elegir como máximo {$grupo->seleccion_maxima} opción(es) en «{$grupo->nombre}».");
            }
        }

        return array_map(function (int $id) use ($opcionesValidas): array {
            ['opcion' => $opcion, 'grupo' => $grupo] = $opcionesValidas[$id];

            return [
                'id_opcion' => $opcion->id_opcion,
                'nombre_grupo' => $grupo->nombre,
                'nombre_opcion' => $opcion->nombre,
                'precio_adicional' => round((float) $opcion->precio_adicional, 2),
            ];
        }, $ids);
    }
}
