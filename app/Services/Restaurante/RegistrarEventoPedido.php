<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\PedidoEvento;

/**
 * Historial de solo inserción de pedidos. Se llama dentro de la misma transacción
 * que el cambio, así el evento nunca existe sin la operación que describe (ni al revés).
 */
class RegistrarEventoPedido
{
    public const PEDIDO_ABIERTO = 'PEDIDO_ABIERTO';
    public const COBRO_REGISTRADO = 'COBRO_REGISTRADO';
    public const PEDIDO_CERRADO = 'PEDIDO_CERRADO';
    public const PEDIDO_ANULADO = 'PEDIDO_ANULADO';
    public const ITEM_AGREGADO = 'ITEM_AGREGADO';
    public const ITEM_MODIFICADO = 'ITEM_MODIFICADO';
    public const ITEM_ANULADO = 'ITEM_ANULADO';
    public const MESA_TRANSFERIDA = 'MESA_TRANSFERIDA';
    public const PEDIDO_UNIDO = 'PEDIDO_UNIDO';
    public const CUENTA_ABSORBIDA = 'CUENTA_ABSORBIDA';
    public const ITEMS_MOVIDOS = 'ITEMS_MOVIDOS';
    public const ITEMS_RECIBIDOS = 'ITEMS_RECIBIDOS';
    public const COMANDA_ENVIADA = 'COMANDA_ENVIADA';
    public const COMANDA_REIMPRESA = 'COMANDA_REIMPRESA';

    public function registrar(int $pedidoId, int $usuarioId, string $tipo, array $datos = [], ?int $detalleId = null): PedidoEvento
    {
        return PedidoEvento::query()->create([
            'id_pedido' => $pedidoId,
            'id_detalle_pedido' => $detalleId,
            'id_usuario' => $usuarioId,
            'tipo' => $tipo,
            'datos' => $datos ?: null,
        ]);
    }
}
