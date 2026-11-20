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

    /**
     * Se invoca dentro de la transacción de envío, con el pedido y sus ítems bloqueados.
     *
     * Por cada plato preparado se descuenta, en la unidad base de cada insumo:
     * - su receta (la de su presentación: taza = 1 filtro, jarra = 2) × cantidad;
     * - por cada opción con producto (sopa del menú, acompañamiento) × veces elegida:
     *   la receta de ese producto si es preparado, o su propio stock si se vende tal cual.
     * Los aportes al mismo insumo se suman en un único consumo por ítem: la restricción
     * única (ítem, insumo) y la del Kardex impiden descontar dos veces ante un reintento.
     */
    public function descontar(Collection $items, int $sucursal, int $usuario): void
    {
        $preparados = $items->filter(fn (PedidoDetalle $item) => $item->tipoRestaurante() === Producto::PREPARADO)->values();
        if ($preparados->isEmpty()) {
            return;
        }

        $modificadores = DB::table('restaurante.pedido_detalle_modificadores')
            ->whereIn('id_detalle_pedido', $preparados->pluck('id_detalle_pedido'))
            ->whereNotNull('id_precio')
            ->get(['id_detalle_pedido', 'id_precio', 'cantidad'])
            ->groupBy('id_detalle_pedido');

        $idsPrecio = $preparados->pluck('id_precio')->merge($modificadores->flatten(1)->pluck('id_precio'))->unique()->values();
        $recetas = DB::table('restaurante.receta_insumos as r')->where('r.id_sucursal', $sucursal)
            ->whereIn('r.id_precio_plato', $idsPrecio)
            ->join('inventario.precio_producto as p', 'p.id_precio', '=', 'r.id_precio_insumo')
            ->get(['r.id_precio_plato', 'r.cantidad', 'p.id_producto', 'p.factor_conversion', 'p.estado'])
            ->groupBy('id_precio_plato');
        $directas = DB::table('inventario.precio_producto as p')
            ->join('catalogos.productos as prod', 'prod.id_producto', '=', 'p.id_producto')
            ->whereIn('p.id_precio', $modificadores->flatten(1)->pluck('id_precio')->unique()->values())
            ->get(['p.id_precio', 'p.id_producto', 'p.id_producto_stock', 'p.factor_conversion', 'p.estado', 'prod.tipo_producto', 'prod.es_servicio'])
            ->keyBy('id_precio');

        // [id_detalle][id_producto] => cantidad base acumulada
        $consumos = [];
        $sumar = function (PedidoDetalle $item, int $producto, float $base, bool $valido) use (&$consumos): void {
            if (! $valido || $base <= 0) {
                $nombre = Producto::query()->whereKey($producto)->value('nombre') ?? 'un insumo';
                throw new DomainException("Revisa la cantidad, conversión y disponibilidad de {$nombre} en la receta.");
            }
            $consumos[$item->id_detalle_pedido][$producto] = ($consumos[$item->id_detalle_pedido][$producto] ?? 0) + $base;
        };
        $receta = function (PedidoDetalle $item, int $idPrecio, float $veces) use ($recetas, $sumar): void {
            foreach ($recetas->get($idPrecio, collect()) as $linea) {
                $sumar($item, (int) $linea->id_producto, $veces * (float) $linea->cantidad * (float) $linea->factor_conversion, (bool) $linea->estado);
            }
        };

        foreach ($preparados as $item) {
            $receta($item, (int) $item->id_precio, (float) $item->cantidad);
            foreach ($modificadores->get($item->id_detalle_pedido, collect()) as $opcion) {
                $precio = $directas->get($opcion->id_precio);
                if (! $precio) {
                    continue;
                }
                if ($precio->tipo_producto === Producto::PREPARADO) {
                    $receta($item, (int) $opcion->id_precio, (float) $opcion->cantidad);
                } elseif (! $precio->es_servicio) {
                    // Producto que se vende tal cual (o variante con stock propio): sale de su inventario.
                    $sumar($item, (int) ($precio->id_producto_stock ?? $precio->id_producto),
                        (float) $opcion->cantidad * (float) $precio->factor_conversion, (bool) $precio->estado);
                }
            }
        }
        if ($consumos === []) {
            return;
        }

        $productos = Producto::query()->whereKey(collect($consumos)->flatMap(fn (array $p) => array_keys($p))->unique()->values()->all())
            ->get()->keyBy('id_producto');
        $filas = collect($consumos)->flatMap(fn (array $porProducto, int $detalle) => collect($porProducto)
            ->map(fn (float $base, int $producto): array => ['detalle' => $detalle, 'producto' => $producto, 'base' => round($base, 4)])->values())
            // Orden estable por producto: evita interbloqueos entre envíos concurrentes.
            ->sortBy([['producto', 'asc'], ['detalle', 'asc']]);

        foreach ($filas as ['detalle' => $detalle, 'producto' => $id, 'base' => $base]) {
            $producto = $productos[$id];
            if (! $producto->estado || $producto->tipo_producto === Producto::PREPARADO || $producto->es_servicio || $base <= 0) {
                throw new DomainException("Revisa la cantidad, conversión y disponibilidad de {$producto->nombre} en la receta.");
            }
            $consumo = DB::table('restaurante.consumos_insumos')->insertGetId([
                'id_sucursal' => $sucursal, 'id_detalle_pedido' => $detalle, 'id_producto' => $id,
                'cantidad_base' => $base, 'lotes' => '{}', 'consumido_en' => now(),
            ], 'id_consumo');
            $origen = new OrigenMovimiento(OrigenMovimiento::CONSUMO_RECETA, $consumo, "ITEM-{$detalle}", $usuario);
            $movimiento = $this->inventario->disminuirBase($producto, $sucursal, $base, $origen);
            DB::table('restaurante.consumos_insumos')->where('id_consumo', $consumo)->update([
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
