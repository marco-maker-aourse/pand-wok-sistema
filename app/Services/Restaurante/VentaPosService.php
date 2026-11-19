<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Venta;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta único del restaurante. Todo se vende como una cuenta (pedido):
 * mostrador/para llevar, delivery o mesa. Así cada plato llega a cocina, descuenta su
 * receta y queda en los reportes, y las bebidas se entregan directo.
 *
 * - enviar(): abre la cuenta si es nueva, guarda las líneas y envía lo pendiente a cocina.
 * - cobrar(): lo mismo y además cobra todo lo que falte de la cuenta, en una sola transacción.
 *
 * Idempotencia: cada operación trae una clave. La cuenta nueva se abre con "pos:{clave}" y el
 * cobro usa la clave como clave_cobro; repetir la petición devuelve la misma cuenta/venta.
 */
class VentaPosService
{
    public function __construct(
        private readonly GestionarPedidoService $pedidos,
        private readonly EnviarComandaService $envios,
        private readonly CobrarPedidoService $cobros,
    ) {}

    /** @return array{pedido: Pedido, comandas: int} */
    public function enviar(?int $pedidoId, array $datos, int $usuario, int $sucursal): array
    {
        return DB::transaction(function () use ($pedidoId, $datos, $usuario, $sucursal): array {
            $pedido = $this->prepararCuenta($pedidoId, $datos, $usuario, $sucursal);
            $comandas = $this->enviarPendientes($pedido, $datos['clave'], $usuario, $sucursal);

            if (! $comandas && empty($datos['items']) && $pedidoId !== null) {
                throw new DomainException('No hay ítems nuevos para enviar a cocina.');
            }

            return ['pedido' => $pedido->refresh(), 'comandas' => $comandas];
        }, 3);
    }

    /** @return array{pedido: Pedido, venta: Venta, nueva: bool} */
    public function cobrar(?int $pedidoId, array $datos, int $usuario, int $sucursal, bool $permiteDescuento): array
    {
        return DB::transaction(function () use ($pedidoId, $datos, $usuario, $sucursal, $permiteDescuento): array {
            // Reintento de un cobro ya registrado: se devuelve tal cual, sin recalcular.
            if ($previa = $this->ventaPrevia($pedidoId, $datos['clave'], $sucursal)) {
                return ['pedido' => Pedido::query()->findOrFail($previa->id_pedido), 'venta' => $previa, 'nueva' => false];
            }

            $pedido = $this->prepararCuenta($pedidoId, $datos, $usuario, $sucursal);
            $this->enviarPendientes($pedido, $datos['clave'], $usuario, $sucursal);

            $items = $pedido->detalles()
                ->where('estado', '!=', PedidoDetalle::ANULADO)
                ->orderBy('id_detalle_pedido')
                ->get()
                ->map(fn (PedidoDetalle $item): array => [
                    'id_detalle_pedido' => $item->id_detalle_pedido,
                    'cantidad' => round((float) $item->cantidad - (float) $item->cantidad_cobrada, 3),
                    'cobrada' => round((float) $item->cantidad_cobrada, 3),
                ])
                ->filter(fn (array $item) => $item['cantidad'] > 0)
                ->values()
                ->all();

            if ($items === []) {
                throw new DomainException('La cuenta no tiene nada por cobrar.');
            }

            ['venta' => $venta, 'nueva' => $nueva] = $this->cobros->ejecutar($pedido->id_pedido, $sucursal, $usuario, [
                'clave_cobro' => $datos['clave'],
                'tipo_comprobante' => $datos['tipo_comprobante'],
                'id_cliente' => $datos['id_cliente'] ?? null,
                'descuento_global' => $datos['descuento_global'] ?? 0,
                'items' => $items,
                'pagos' => $datos['pagos'],
            ], $permiteDescuento);

            return ['pedido' => $pedido->refresh(), 'venta' => $venta, 'nueva' => $nueva];
        }, 3);
    }

    /** Cuenta existente (abierta, de la sucursal) o nueva; agrega las líneas del ticket. */
    private function prepararCuenta(?int $pedidoId, array $datos, int $usuario, int $sucursal): Pedido
    {
        if ($pedidoId === null) {
            $pedido = $this->pedidos->abrir([
                'tipo' => $datos['tipo'],
                'id_mesa' => $datos['id_mesa'] ?? null,
                'comensales' => $datos['comensales'] ?? null,
                'nombre_contacto' => $datos['nombre_contacto'] ?? null,
                'telefono_contacto' => $datos['telefono_contacto'] ?? null,
                'direccion_entrega' => $datos['direccion_entrega'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'clave_idempotencia' => 'pos:'.$datos['clave'],
            ], $usuario, $sucursal);
        } else {
            $pedido = Pedido::query()->where('id_sucursal', $sucursal)->find($pedidoId)
                ?? throw new DomainException('La cuenta no existe en esta sucursal.');
        }

        if (! $pedido->estaAbierto()) {
            throw new DomainException("La cuenta #{$pedido->numero} ya no está abierta.");
        }

        if (! empty($datos['items'])) {
            $this->pedidos->agregarItems($pedido->id_pedido, $datos['items'], $usuario, $sucursal);
        }

        return $pedido;
    }

    /** Envía a cocina lo pendiente; devuelve cuántas comandas se generaron. */
    private function enviarPendientes(Pedido $pedido, string $clave, int $usuario, int $sucursal): int
    {
        $pendientes = $pedido->detalles()->where('estado', PedidoDetalle::PENDIENTE)->exists();
        if (! $pendientes) {
            return 0;
        }

        return $this->envios->enviar($pedido->id_pedido, "pos:{$clave}:envio", $usuario, $sucursal)['comandas']->count();
    }

    private function ventaPrevia(?int $pedidoId, string $clave, int $sucursal): ?Venta
    {
        $pedido = $pedidoId ?? Pedido::query()->where('clave_idempotencia', 'pos:'.$clave)->where('id_sucursal', $sucursal)->value('id_pedido');
        if (! $pedido) {
            return null;
        }

        return Venta::query()->where('id_pedido', $pedido)->where('id_sucursal', $sucursal)->where('clave_cobro', $clave)->first();
    }
}
