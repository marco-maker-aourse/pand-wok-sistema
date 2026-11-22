<?php

namespace App\Services\Ventas;

use App\Models\MetodoPago;
use App\Models\PagoVenta;
use App\Models\ProductoPresentacion;
use App\Models\TipoComprobante;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use App\Services\Comprobantes\ReservarCorrelativoService;
use DomainException;
use Illuminate\Support\Facades\DB;

class RegistrarVentaService
{
    public function __construct(private readonly GestionarExistenciaService $inventory, private readonly ReservarCorrelativoService $documents) {}

    /**
     * La venta, pagos y salida de stock se confirman juntos o se revierten juntos.
     */
    public function execute(array $data): Venta
    {
        return $this->registrar($data, false);
    }

    /** Comando interno construido con líneas bloqueadas y precios congelados del pedido. */
    public function registrarPedido(array $data): Venta
    {
        return $this->registrar($data, true);
    }

    private function registrar(array $data, bool $esPedido): Venta
    {
        return DB::transaction(function () use ($data, $esPedido): Venta {
            // El turno se bloquea antes de precio, stock y comprobante. El cierre de caja
            // no puede coexistir con una venta a medio registrar.
            $turno = TurnoCaja::query()
                ->whereKey($data['id_turno'])
                ->where('id_usuario', $data['id_usuario'])
                ->where('id_sucursal', $data['id_sucursal'])
                ->where('estado', 'ABIERTO')
                ->lockForUpdate()
                ->first();

            if (! $turno) {
                throw new DomainException('El turno de caja fue cerrado o ya no está disponible.');
            }

            $lines = $this->prepareLines($data, $esPedido);
            $receiptType = TipoComprobante::query()
                ->whereKey($data['id_tipo_comprobante'])
                ->where('estado', true)
                ->firstOrFail();
            if ($esPedido && (float) ($data['descuento_global'] ?? 0) > 0) {
                $gravadas = collect($lines)->filter(fn (array $line) => $line['presentation']->producto->afecta_igv)->sum('subtotal');
                if (round((float) $data['descuento_global'], 2) > round($gravadas, 2)) {
                    throw new DomainException('El descuento solo puede aplicarse sobre el importe gravado seleccionado.');
                }
            }
            $document = $this->documents->execute($data['id_sucursal'], $receiptType->codigo_sunat);
            $totals = $this->totals($lines, (float) ($data['descuento_global'] ?? 0), (float) $document['igv']);

            if ($esPedido && $totals['total'] <= 0) {
                throw new DomainException('El cobro debe tener un importe mayor que cero.');
            }

            $sale = Venta::create([
                ...($esPedido ? array_intersect_key($data, array_flip(['id_pedido', 'clave_cobro', 'huella_cobro'])) : []),
                'id_sucursal' => $data['id_sucursal'], 'id_cliente' => $data['id_cliente'],
                'id_usuario' => $data['id_usuario'], 'id_turno' => $data['id_turno'],
                'id_tipo_comprobante' => $receiptType->id_tipo_comprobante,
                'serie' => $document['serie'], 'correlativo' => $document['correlativo'],
                'op_gravadas' => $totals['gravadas'], 'op_exoneradas' => $totals['exoneradas'],
                'igv' => $totals['igv'], 'descuento_global' => $totals['descuento'],
                'total_venta' => $totals['total'],
            ]);

            foreach ($lines as $line) {
                $sale->detalles()->create([
                    ...($esPedido ? ['id_detalle_pedido' => $line['id_detalle_pedido']] : []),
                    'id_precio' => $line['presentation']->id_precio,
                    'cantidad' => $line['quantity'],
                    'precio_unitario' => $line['price'], 'subtotal' => $line['subtotal'],
                ]);
                $producto = $line['presentation']->producto;
                if (! $producto->es_servicio && $line['descontar_stock']) {
                    $this->inventory->decrease($line['presentation'], $data['id_sucursal'], $line['quantity'], OrigenMovimiento::venta($sale));
                }
            }

            $this->registerPayments($sale, $data['pagos'], $totals['total'], $esPedido);

            return $sale->load('detalles', 'pagos');
        }, attempts: 3);
    }

    private function prepareLines(array $data, bool $esPedido): array
    {
        return array_map(function (array $detail) use ($data, $esPedido): array {
            // El POS toma el precio de la presentación; la cuenta usa el precio que
            // congeló el servidor al tomar el pedido.
            $presentation = ProductoPresentacion::query()->with('producto')
                ->whereKey($detail['id_precio'])
                ->when(! $esPedido, fn ($query) => $query->where('estado', true))
                ->lockForUpdate()
                ->firstOrFail();
            // La UI permite fracciones; se persiste a la precisión acordada con Kardex.
            $quantity = round((float) $detail['cantidad'], 4);
            if ((float) $presentation->factor_conversion <= 0) {
                throw new DomainException('La presentación seleccionada no tiene una conversión válida.');
            }
            $price = (float) ($esPedido ? $detail['precio_unitario'] : $presentation->precio_venta);
            return ['presentation' => $presentation, 'quantity' => $quantity,
                'id_detalle_pedido' => $esPedido ? $detail['id_detalle_pedido'] : null,
                'descontar_stock' => ! $esPedido || $detail['descontar_stock'],
                'price' => $price,
                'subtotal' => $esPedido ? $detail['subtotal'] : round($quantity * $price, 2)];
        }, $data['detalles']);
    }

    private function totals(array $lines, float $discount, float $igvRate): array
    {
        $taxable = 0.0;
        $exempt = 0.0;
        foreach ($lines as $line) {
            $line['presentation']->producto->afecta_igv ? $taxable += $line['subtotal'] : $exempt += $line['subtotal'];
        }
        $gross = $taxable + $exempt;
        if ($discount > $gross) {
            throw new DomainException('El descuento no puede superar el importe de la venta.');
        }
        $taxableAfterDiscount = max(0, $taxable - $discount);
        $igv = round($taxableAfterDiscount * $igvRate / (100 + $igvRate), 2);

        return ['gravadas' => round($taxableAfterDiscount - $igv, 2),
            'exoneradas' => round($exempt, 2), 'igv' => $igv,
            'descuento' => round($discount, 2), 'total' => round($gross - $discount, 2)];
    }

    private function registerPayments(Venta $sale, array $payments, float $total, bool $esPedido): void
    {
        // Un cobro único equivale al total calculado por el servidor; nunca al subtotal enviado por React.
        if (count($payments) === 1 && ! isset($payments[0]['monto'])) {
            $payments[0]['monto'] = $total;
        }
        $received = round(array_sum(array_column($payments, 'monto')), 2);
        if ($esPedido ? $received !== $total : abs($received - $total) > 0.01) {
            throw new DomainException('La suma de pagos debe coincidir exactamente con el total de la venta.');
        }
        foreach ($payments as $payment) {
            $method = MetodoPago::query()->when($esPedido, fn ($query) => $query->where('estado', true))->find($payment['id_metodo']);
            if (! $method) {
                throw new DomainException('Uno de los medios de pago ya no está disponible.');
            }
            $cashReceived = null;
            $change = null;
            if ($method->tipo === MetodoPago::EFECTIVO) {
                if (! isset($payment['monto_recibido']) || ! is_numeric($payment['monto_recibido'])) {
                    throw new DomainException('Indica cuánto efectivo recibiste del cliente.');
                }
                $cashReceived = round((float) $payment['monto_recibido'], 2);
                if ($cashReceived < (float) $payment['monto']) {
                    throw new DomainException('El efectivo recibido no puede ser menor al importe asignado a efectivo.');
                }
                $change = round($cashReceived - (float) $payment['monto'], 2);
            }
            $reference = $payment['nro_referencia'] ?? null;
            if ($method->requiere_referencia && blank($reference)) {
                throw new DomainException("El método {$method->nombre} requiere número de referencia.");
            }
            // Los métodos no efectivos se confirman presencialmente por el cajero.
            // La aplicación no declara verificado un pago de billetera o terminal físico.
            $commission = $method->tipo === MetodoPago::PASARELA
                ? round(((float) $payment['monto'] * (float) $method->comision_porcentaje / 100) + (float) $method->comision_fija, 2)
                : 0.0;
            if ($commission > (float) $payment['monto']) { throw new DomainException('La comisión no puede superar el monto cobrado.'); }
            PagoVenta::create(['id_venta' => $sale->id_venta, 'id_metodo' => $method->id_metodo,
                'monto' => $payment['monto'], 'nro_referencia' => $reference,
                'monto_recibido' => $cashReceived, 'vuelto' => $change,
                'comision_pasarela' => $commission, 'estado_pago' => 'COMPLETADO']);
        }
    }
}
