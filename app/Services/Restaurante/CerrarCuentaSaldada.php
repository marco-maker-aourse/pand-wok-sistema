<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;

class CerrarCuentaSaldada
{
    public function __construct(private readonly BloqueoSalonService $salon, private readonly RegistrarEventoPedido $eventos) {}

    /** El llamador conserva el bloqueo del pedido y la transacción que cambió el saldo. */
    public function ejecutar(Pedido $pedido, int $usuario): void
    {
        $vigentes = $pedido->detalles()->where('estado', '!=', PedidoDetalle::ANULADO);
        if (! (clone $vigentes)->where('cantidad_cobrada', '>', 0)->exists()
            || $vigentes->whereColumn('cantidad_cobrada', '<', 'cantidad')->exists()) {
            return;
        }

        $pedido->update(['estado' => Pedido::CERRADO, 'cerrado_en' => now(), 'id_usuario_cierre' => $usuario]);
        $this->salon->liberarMesa($pedido->id_mesa, $pedido->id_sucursal);
        $this->eventos->registrar($pedido->id_pedido, $usuario, RegistrarEventoPedido::PEDIDO_CERRADO);
    }
}
