<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Venta;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta único del restaurante. Todo se vende como una cuenta (pedido):
 * mostrador/para llevar, delivery o mesa. El envío a cocina se solicita por separado
 * o al cobrar. La venta y el inventario se registran en cualquiera de los dos casos.
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

    public function guardar(?int $pedidoId, array $datos, int $usuario, int $sucursal): Pedido
    {
        if (empty($datos['items'])) {
            throw new DomainException('Agrega ítems al pedido para guardarlo.');
        }

        return DB::transaction(fn () => $this->prepararCuenta($pedidoId, $datos, $usuario, $sucursal), 3);
    }

    /** @return array{pedido: Pedido, comandas: int, sin_imprimir: list<string>} */
    public function enviar(?int $pedidoId, array $datos, int $usuario, int $sucursal): array
    {
        return DB::transaction(function () use ($pedidoId, $datos, $usuario, $sucursal): array {
            $pedido = $this->prepararCuenta($pedidoId, $datos, $usuario, $sucursal);
            $envio = $this->enviarPendientes($pedido, $datos['clave'], $usuario, $sucursal);

            if ($envio === null && empty($datos['items']) && $pedidoId !== null) {
                throw new DomainException('No hay ítems nuevos para enviar a cocina.');
            }

            return [
                'pedido' => $pedido->refresh(),
                'comandas' => $envio ? $envio['comandas']->count() : 0,
                'sin_imprimir' => $envio['sin_imprimir'] ?? [],
            ];
        }, 3);
    }

    /** @return array{pedido: Pedido, venta: Venta, nueva: bool, sin_imprimir?: list<string>} */
    public function cobrar(?int $pedidoId, array $datos, int $usuario, int $sucursal, bool $permiteDescuento): array
    {
        return DB::transaction(function () use ($pedidoId, $datos, $usuario, $sucursal, $permiteDescuento): array {
            // Reintento de un cobro ya registrado: se devuelve tal cual, sin recalcular.
            if ($previa = $this->ventaPrevia($pedidoId, $datos['clave'], $sucursal)) {
                return ['pedido' => Pedido::query()->findOrFail($previa->id_pedido), 'venta' => $previa, 'nueva' => false];
            }

            $pedido = $this->prepararCuenta($pedidoId, $datos, $usuario, $sucursal);
            $sinImprimir = ($datos['enviar_a_cocina'] ?? false)
                ? ($this->enviarPendientes($pedido, $datos['clave'], $usuario, $sucursal)['sin_imprimir'] ?? [])
                : [];

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

            return ['pedido' => $pedido->refresh(), 'venta' => $venta, 'nueva' => $nueva, 'sin_imprimir' => $sinImprimir];
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

    /** Devuelve null si no había ítems por enviar; si no, el resultado del envío a cocina. */
    private function enviarPendientes(Pedido $pedido, string $clave, int $usuario, int $sucursal): ?array
    {
        if (! $pedido->detalles()->pendientesDeEnvio()->exists()) {
            return null;
        }

        return $this->envios->enviar($pedido->id_pedido, "pos:{$clave}:envio", $usuario, $sucursal);
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
