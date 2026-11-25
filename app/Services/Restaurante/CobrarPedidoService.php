<?php

namespace App\Services\Restaurante;

use App\Models\TipoDocumento;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\TipoComprobante;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Services\Ventas\CatalogoPosService;
use App\Services\Ventas\RegistrarVentaService;
use DomainException;
use Illuminate\Support\Facades\DB;

class CobrarPedidoService
{
    public function __construct(
        private readonly RegistrarVentaService $ventas,
        private readonly CerrarCuentaSaldada $cierre,
        private readonly RegistrarEventoPedido $eventos,
        private readonly CatalogoPosService $catalogo,
        private readonly ConsumoRecetaService $consumo,
    ) {}

    /** @return array{venta: Venta, nueva: bool} */
    public function ejecutar(int $pedidoId, int $sucursal, int $usuario, array $datos, bool $permiteDescuento): array
    {
        $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($pedidoId, $sucursal, $usuario, $datos, $permiteDescuento, $huella): array {
            $pedido = Pedido::query()->where('id_sucursal', $sucursal)->lockForUpdate()->findOrFail($pedidoId);
            $previa = Venta::query()->where('id_pedido', $pedidoId)->where('id_sucursal', $sucursal)
                ->where('clave_cobro', $datos['clave_cobro'])->first();
            if ($previa) {
                if ((int) $previa->id_usuario !== $usuario || $previa->huella_cobro !== $huella) {
                    throw new DomainException('La clave de cobro ya se usó con otros datos.');
                }

                return ['venta' => $previa, 'nueva' => false];
            }
            if (! $pedido->estaAbierto()) {
                throw new DomainException('La cuenta ya no está abierta.');
            }

            $turno = TurnoCaja::query()->where('id_sucursal', $sucursal)->where('id_usuario', $usuario)
                ->where('estado', 'ABIERTO')->latest('id_turno')->value('id_turno');
            if (! $turno) {
                throw new DomainException('Abre un turno de caja para cobrar esta cuenta.');
            }
            $descuento = round((float) ($datos['descuento_global'] ?? 0), 2);
            if ($descuento > 0 && ! $permiteDescuento) {
                throw new DomainException('Tu usuario no tiene permiso para aplicar descuentos.');
            }

            $items = $pedido->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)
                ->with('producto:id_producto,tipo_producto')->orderBy('id_detalle_pedido')->lockForUpdate()->get()->keyBy('id_detalle_pedido');
            $lineas = [];
            $cantidades = [];
            foreach ($datos['items'] as $solicitud) {
                $id = (int) $solicitud['id_detalle_pedido'];
                $item = $items->get($id);
                if (! $item || isset($cantidades[$id])) {
                    throw new DomainException('Uno de los ítems no está disponible en esta cuenta.');
                }
                $cobrada = round((float) $item->cantidad_cobrada, 3);
                $cantidad = round((float) $solicitud['cantidad'], 3);
                $nuevo = round($cobrada + $cantidad, 3);
                if ($cobrada !== round((float) $solicitud['cobrada'], 3) || $cantidad <= 0 || $nuevo > (float) $item->cantidad) {
                    throw new DomainException('El saldo de la cuenta cambió. Actualiza y revisa las cantidades por cobrar.');
                }

                $precio = (float) $item->precio_unitario;
                $lineas[] = [
                    'id_detalle_pedido' => $id,
                    'id_precio' => $item->id_precio,
                    'descontar_stock' => $item->tipoRestaurante() !== Producto::PREPARADO,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => round(ArmarItemPedidoService::subtotal($nuevo, $precio) - ArmarItemPedidoService::subtotal($cobrada, $precio), 2),
                ];
                $cantidades[$id] = $nuevo;
            }

            $factura = $datos['tipo_comprobante'] === 'FACTURA';
            $nota = $datos['tipo_comprobante'] === 'NOTA_VENTA';
            $cliente = $this->cliente($datos['id_cliente'] ?? null, $pedido, $factura);
            // SUNAT: una boleta por más de S/ 700 debe identificar al cliente (DNI u otro documento).
            // La nota de venta no es comprobante electrónico: no aplica.
            $totalCobro = round(collect($lineas)->sum('subtotal') - $descuento, 2);
            if (! $factura && ! $nota && $totalCobro > self::LIMITE_BOLETA_SIN_IDENTIFICAR && ! self::clienteIdentificado($cliente)) {
                throw new DomainException('Para boletas mayores a S/ 700, identifica al cliente con su DNI (o emite una nota de venta).');
            }
            $codigo = $factura ? TipoComprobante::FACTURA : ($nota ? Venta::NOTA_VENTA : TipoComprobante::BOLETA);
            $tipo = TipoComprobante::query()->where('codigo_sunat', $codigo)->where('estado', true)->value('id_tipo_comprobante');
            if (! $tipo) {
                throw new DomainException('El tipo de comprobante todavía no está habilitado.');
            }

            // Un plato atendido fuera de cocina registra su receta en el primer cobro
            // de la línea. Los platos enviados ya registraron su consumo en la comanda.
            $sinEnvio = $items->only(array_keys($cantidades))
                ->filter(fn (PedidoDetalle $item) => $item->estaPendiente() && ! $item->tieneCobro());
            $this->consumo->descontar($sinEnvio, $sucursal, $usuario);

            $venta = $this->ventas->registrarPedido([
                'id_sucursal' => $sucursal, 'id_usuario' => $usuario, 'id_turno' => $turno,
                'id_pedido' => $pedidoId, 'clave_cobro' => $datos['clave_cobro'], 'huella_cobro' => $huella,
                'id_cliente' => $cliente->id_cliente, 'id_tipo_comprobante' => $tipo,
                'detalles' => $lineas, 'pagos' => $datos['pagos'], 'descuento_global' => $descuento,
            ]);

            foreach ($cantidades as $id => $cantidad) {
                $items[$id]->update(['cantidad_cobrada' => $cantidad]);
            }
            $this->eventos->registrar($pedidoId, $usuario, RegistrarEventoPedido::COBRO_REGISTRADO, [
                'id_venta' => $venta->id_venta, 'comprobante' => "{$venta->serie}-{$venta->correlativo}", 'total' => (float) $venta->total_venta,
            ]);
            $this->cierre->ejecutar($pedido, $usuario);

            return ['venta' => $venta, 'nueva' => true];
        }, 3);
    }

    /** Monto desde el cual SUNAT exige identificar al adquirente en una boleta. */
    public const LIMITE_BOLETA_SIN_IDENTIFICAR = 700.0;

    /** Un cliente con documento real; "público general" (ceros o tipo 0) no cuenta. */
    public static function clienteIdentificado(Cliente $cliente): bool
    {
        $codigo = (string) ($cliente->tipoDocumento?->codigo_sunat ?? '0');

        return $codigo !== '0' && $codigo !== '-' && ! preg_match('/^0+$/', (string) $cliente->num_documento);
    }

    private function cliente(?int $id, Pedido $pedido, bool $factura): Cliente
    {
        $identificador = $id ?? $pedido->id_cliente;
        $consulta = Cliente::query()->where('estado', true)->with('tipoDocumento');
        $cliente = $identificador ? $consulta->find($identificador) : $this->catalogo->clienteGenerico()?->load('tipoDocumento');
        if (! $cliente) {
            throw new DomainException('Selecciona un cliente activo o configura el cliente de público general.');
        }
        if ($factura && ($cliente->tipoDocumento?->codigo_sunat !== TipoDocumento::RUC || ! preg_match('/^\d{11}$/', $cliente->num_documento))) {
            throw new DomainException('La factura requiere un cliente registrado con RUC.');
        }

        return $cliente;
    }
}
