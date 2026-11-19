<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Movimientos de cuentas en el salón: cambiar de mesa, unir dos cuentas y pasar
 * ítems de una cuenta a otra (separar). Requieren el permiso pedidos.transferir.
 *
 * No se mueven ítems con cobro registrado: su venta ya apunta a ese ítem y moverlo
 * rompería la trazabilidad del cobro parcial.
 */
class TransferirPedidoService
{
    public function __construct(
        private readonly BloqueoSalonService $bloqueo,
        private readonly RegistrarEventoPedido $eventos,
    ) {}

    /** Lleva la cuenta a una mesa libre; la mesa anterior queda disponible. */
    public function cambiarMesa(int $pedidoId, int $mesaDestinoId, int $usuarioId, int $sucursalId): Pedido
    {
        return DB::transaction(function () use ($pedidoId, $mesaDestinoId, $usuarioId, $sucursalId): Pedido {
            $pedido = $this->bloqueo->pedidoAbierto($pedidoId, $sucursalId);
            if ($pedido->tipo !== Pedido::MESA) {
                throw new DomainException('Solo una cuenta de mesa puede cambiar de mesa.');
            }
            if ((int) $pedido->id_mesa === $mesaDestinoId) {
                throw new DomainException('La cuenta ya está en esa mesa.');
            }

            // Ambas mesas en orden de id para evitar bloqueos cruzados.
            $ids = [(int) $pedido->id_mesa, $mesaDestinoId];
            sort($ids);
            $mesas = collect($ids)->mapWithKeys(fn (int $id) => [$id => $this->bloqueo->mesa($id, $sucursalId)]);
            $origen = $mesas[(int) $pedido->id_mesa];
            $destino = $mesas[$mesaDestinoId];

            $this->bloqueo->ocuparMesa($destino);
            $pedido->update(['id_mesa' => $destino->id_mesa]);
            $this->bloqueo->liberarMesa($origen->id_mesa, $sucursalId);

            $this->eventos->registrar($pedido->id_pedido, $usuarioId, RegistrarEventoPedido::MESA_TRANSFERIDA, [
                'mesa_origen' => $origen->codigo,
                'mesa_destino' => $destino->codigo,
            ]);

            return $pedido->refresh();
        });
    }

    /** Pasa todos los ítems vigentes del origen al destino; el origen queda UNIDO y libera su mesa. */
    public function unir(int $origenId, int $destinoId, int $usuarioId, int $sucursalId): Pedido
    {
        return DB::transaction(function () use ($origenId, $destinoId, $usuarioId, $sucursalId): Pedido {
            [$origen, $destino] = $this->bloqueo->dosPedidosAbiertos($origenId, $destinoId, $sucursalId);

            $items = $this->itemsMovibles($origen, null);
            PedidoDetalle::query()->whereKey($items->modelKeys())->update(['id_pedido' => $destino->id_pedido]);

            $origen->update([
                'estado' => Pedido::UNIDO,
                'id_pedido_destino' => $destino->id_pedido,
                'cerrado_en' => now(),
                'id_usuario_cierre' => $usuarioId,
            ]);
            $this->bloqueo->liberarMesa($origen->id_mesa, $sucursalId);
            $destino->touch();

            $resumen = ['items' => $items->count(), 'pedido_origen' => $origen->numero, 'pedido_destino' => $destino->numero];
            $this->eventos->registrar($origen->id_pedido, $usuarioId, RegistrarEventoPedido::PEDIDO_UNIDO, $resumen);
            $this->eventos->registrar($destino->id_pedido, $usuarioId, RegistrarEventoPedido::CUENTA_ABSORBIDA, $resumen);

            return $destino->refresh();
        });
    }

    /**
     * Pasa ítems concretos a otra cuenta abierta (separar la cuenta). Se mueven líneas
     * completas; el origen sigue abierto aunque quede vacío, para que el mozo decida.
     *
     * @param  array<int>  $detalleIds
     */
    public function moverItems(int $origenId, int $destinoId, array $detalleIds, int $usuarioId, int $sucursalId): Pedido
    {
        $detalleIds = array_values(array_unique(array_map('intval', $detalleIds)));
        if ($detalleIds === []) {
            throw new DomainException('Selecciona al menos un ítem para mover.');
        }

        return DB::transaction(function () use ($origenId, $destinoId, $detalleIds, $usuarioId, $sucursalId): Pedido {
            [$origen, $destino] = $this->bloqueo->dosPedidosAbiertos($origenId, $destinoId, $sucursalId);

            $items = $this->itemsMovibles($origen, $detalleIds);
            if ($items->count() !== count($detalleIds)) {
                throw new DomainException('Algunos ítems no pertenecen a esta cuenta o ya fueron anulados.');
            }

            PedidoDetalle::query()->whereKey($detalleIds)->update(['id_pedido' => $destino->id_pedido]);
            $origen->touch();
            $destino->touch();

            $productos = $items->pluck('nombre_producto')->all();
            $this->eventos->registrar($origen->id_pedido, $usuarioId, RegistrarEventoPedido::ITEMS_MOVIDOS, [
                'pedido_destino' => $destino->numero, 'productos' => $productos,
            ]);
            $this->eventos->registrar($destino->id_pedido, $usuarioId, RegistrarEventoPedido::ITEMS_RECIBIDOS, [
                'pedido_origen' => $origen->numero, 'productos' => $productos,
            ]);

            return $destino->refresh();
        });
    }

    /** Ítems vigentes del pedido (todos o los indicados), validando que ninguno tenga cobro. */
    private function itemsMovibles(Pedido $pedido, ?array $detalleIds)
    {
        $items = $pedido->detalles()
            ->where('estado', '!=', PedidoDetalle::ANULADO)
            ->when($detalleIds !== null, fn ($query) => $query->whereKey($detalleIds))
            ->lockForUpdate()
            ->get();

        $conCobro = $items->first(fn (PedidoDetalle $detalle) => $detalle->tieneCobro());
        if ($conCobro) {
            throw new DomainException("{$conCobro->nombre_producto} ya tiene un cobro registrado y no puede moverse.");
        }

        return $items;
    }
}
