<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\Restaurante\PedidoDetalle;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumoRecetaService
{
    public function __construct(private readonly GestionarExistenciaService $inventario) {}

    /** Se invoca dentro de la transacción de envío, con el pedido y sus ítems bloqueados. */
    public function descontar(Collection $items, int $sucursal, int $usuario): void
    {
        $preparados = $items->filter(fn (PedidoDetalle $item) => $item->tipoRestaurante() === Producto::PREPARADO);
        if ($preparados->isEmpty()) {
            return;
        }

        $recetas = DB::table('restaurante.receta_insumos as r')->where('r.id_sucursal', $sucursal)
            ->whereIn('r.id_precio_plato', $preparados->pluck('id_precio'))
            ->join('inventario.precio_producto as p', 'p.id_precio', '=', 'r.id_precio_insumo')
            ->get(['r.id_precio_plato', 'r.cantidad', 'p.id_producto', 'p.factor_conversion', 'p.estado'])
            ->groupBy('id_precio_plato');
        $movimientos = $preparados->flatMap(fn (PedidoDetalle $item) => $recetas->get($item->id_precio, collect())
            ->map(fn (object $receta): array => ['item' => $item, 'receta' => $receta]))
            ->sortBy('receta.id_producto');
        $productos = Producto::query()->whereKey($movimientos->pluck('receta.id_producto')->unique()->values()->all())->get()->keyBy('id_producto');

        foreach ($movimientos as ['item' => $item, 'receta' => $receta]) {
            $producto = $productos[$receta->id_producto];
            $base = round((float) $item->cantidad * (float) $receta->cantidad * (float) $receta->factor_conversion, 4);
            if (! $receta->estado || ! $producto->estado || $producto->tipo_producto !== Producto::INSUMO || $base <= 0) {
                throw new DomainException("Revisa la cantidad, conversión y disponibilidad de {$producto->nombre} en la receta.");
            }
            $id = DB::table('restaurante.consumos_insumos')->insertGetId([
                'id_sucursal' => $sucursal, 'id_detalle_pedido' => $item->id_detalle_pedido, 'id_producto' => $producto->id_producto,
                'cantidad_base' => $base, 'lotes' => '{}', 'consumido_en' => now(),
            ], 'id_consumo');
            $origen = new OrigenMovimiento(OrigenMovimiento::CONSUMO_RECETA, $id, "ITEM-{$item->id_detalle_pedido}", $usuario);
            $movimiento = $this->inventario->disminuirBase($producto, $sucursal, $base, $origen);
            DB::table('restaurante.consumos_insumos')->where('id_consumo', $id)->update([
                'costo_unitario' => $movimiento['costo'], 'lotes' => json_encode($movimiento['lotes'], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /** Al anular, solo devuelve lo confirmado como recuperable y aún pendiente de preparación. */
    public function resolverAnulacion(Collection $items, int $sucursal, int $usuario, bool $recuperar): void
    {
        $enviados = $items->filter(fn (PedidoDetalle $item) => ! $item->estaPendiente())->keyBy('id_detalle_pedido');
        if ($enviados->isEmpty()) {
            return;
        }

        $movimientos = DB::table('restaurante.consumos_insumos')->where('id_sucursal', $sucursal)
            ->whereIn('id_detalle_pedido', $enviados->keys())->where('estado', 'CONSUMIDO')
            ->orderBy('id_producto')->orderBy('id_consumo')->lockForUpdate()->get();
        foreach ($movimientos as $movimiento) {
            $devuelve = $recuperar && $enviados[$movimiento->id_detalle_pedido]->estado === PedidoDetalle::ENVIADO;
            if ($devuelve) {
                $origen = new OrigenMovimiento(OrigenMovimiento::REVERSION_RECETA, $movimiento->id_consumo, "ITEM-{$movimiento->id_detalle_pedido}", $usuario);
                $this->inventario->reponerConsumo($movimiento->id_producto, $sucursal, (float) $movimiento->cantidad_base,
                    (float) $movimiento->costo_unitario, json_decode($movimiento->lotes, true, flags: JSON_THROW_ON_ERROR), $origen);
            }
            DB::table('restaurante.consumos_insumos')->where('id_consumo', $movimiento->id_consumo)->update([
                'estado' => $devuelve ? 'REVERTIDO' : 'MERMA', 'resuelto_en' => now(),
            ]);
        }
    }
}
