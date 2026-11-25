<?php

namespace App\Services\Comprobantes;

use App\Models\TipoComprobante;
use App\Models\Restaurante\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Services\Configuracion\DatosGeneralesService;
use App\Support\MontoEnLetras;
use Carbon\CarbonInterface;

/**
 * Construye el contrato único de la representación impresa.
 *
 * Tanto el PDF como el ticket térmico parten de estos datos persistidos; el
 * navegador nunca decide importes, cliente ni detalle fiscal del comprobante.
 */
class PresentarComprobanteService
{
    public function __construct(private readonly DatosGeneralesService $datosGenerales = new DatosGeneralesService) {}

    public function paraVenta(Venta $venta): array
    {
        $venta->loadMissing([
            'cliente.tipoDocumento:id_tipo_documento,codigo_sunat,nombre',
            'comprobante:id_tipo_comprobante,descripcion,codigo_sunat',
            'detalles.precio.producto:id_producto,nombre,codigo_barras,afecta_igv,id_unidad_base',
            'detalles.precio.producto.unidadBase:id_unidad,nombre,codigo_sunat',
            'detalles.precio.presentacion:id_presentacion,nombre',
            'pagos.metodo:id_metodo,nombre,tipo',
            'turno.terminal:id_terminal,nombre,codigo',
        ]);

        $emisor = $this->datosGenerales->emisor((int) $venta->id_sucursal);
        $esFactura = $venta->comprobante?->codigo_sunat === TipoComprobante::FACTURA;
        // Nota de venta: documento interno, sin valor tributario, sin QR ni consulta SUNAT.
        $esNota = $venta->comprobante?->codigo_sunat === Venta::NOTA_VENTA;
        $emision = $venta->fecha_emision?->copy()->setTimezone('America/Lima');

        $documento = [
            'codigo_sunat' => $venta->comprobante?->codigo_sunat,
            'titulo' => $esNota ? 'NOTA DE VENTA' : ($esFactura ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA'),
            'serie' => $venta->serie,
            'correlativo' => str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
            'numero' => "{$venta->serie}-".str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
            'emitido_en' => $venta->fecha_emision?->toIso8601String(),
            // Fecha y hora de emisión ya en hora de Perú, iguales a IssueDate/IssueTime del XML.
            'fecha' => $emision?->format('d/m/Y'),
            'hora' => $emision?->format('H:i:s'),
            'moneda' => 'SOLES',
            'codigo_moneda' => 'PEN',
            // Las ventas del POS se cobran al momento (el XML declara FormaPago = Contado).
            'forma_pago' => 'CONTADO',
        ];

        $codigoDocumento = $venta->cliente?->tipoDocumento?->codigo_sunat ?? '0';
        $numeroDocumento = $venta->cliente?->num_documento ?? '00000000';
        $cliente = [
            'codigo_sunat' => $codigoDocumento,
            'nombre' => $venta->cliente?->nombre_razon_social ?? 'PÚBLICO EN GENERAL',
            'tipo_documento' => $venta->cliente?->tipoDocumento?->nombre ?? 'Documento',
            'tipo_documento_corto' => self::abreviaturaDocumento($codigoDocumento, $venta->cliente?->tipoDocumento?->nombre),
            'numero_documento' => $numeroDocumento,
            'direccion' => $venta->cliente?->direccion_fiscal,
            // Un consumidor sin documento real ("Otros" o ceros) se imprime como CLIENTE VARIOS.
            'identificado' => $codigoDocumento !== '0' && $codigoDocumento !== '-' && ! preg_match('/^0+$/', (string) $numeroDocumento),
        ];

        $total = (float) $venta->total_venta;
        $totales = [
            'gravadas' => (float) $venta->op_gravadas,
            'exoneradas' => (float) $venta->op_exoneradas,
            'igv' => (float) $venta->igv,
            'igv_porcentaje' => $emisor?->igv_porcentaje !== null ? (float) $emisor->igv_porcentaje : null,
            'descuento' => (float) $venta->descuento_global,
            'total' => $total,
            'total_letras' => MontoEnLetras::soles($total),
        ];

        $cajero = $venta->id_usuario ? User::query()->whereKey($venta->id_usuario)->value('nombres') : null;
        $opciones = DescripcionItems::opciones($venta->detalles->pluck('id_detalle_pedido')->all());

        // Restaurante: de qué cuenta viene el cobro (mesa, para llevar o delivery).
        $pedido = $venta->id_pedido
            ? Pedido::query()->withoutGlobalScopes()->with(['mesa:id_mesa,codigo', 'usuario:id_usuario,nombres'])->find($venta->id_pedido)
            : null;

        return [
            'emisor' => [
                'ruc' => $emisor?->ruc_empresa,
                'razon_social' => $emisor?->razon_social,
                'nombre_comercial' => $emisor?->nombre_comercial,
                'direccion' => $emisor?->direccion_fiscal,
                'pie_ticket' => $emisor?->pie_ticket,
                'establecimiento' => [
                    'codigo' => $emisor?->codigo_establecimiento,
                    'nombre' => $emisor?->establecimiento_nombre,
                    'direccion' => $emisor?->establecimiento_direccion,
                    'telefono' => $emisor?->establecimiento_telefono,
                ],
            ],
            'documento' => $documento,
            'cliente' => $cliente,
            'pedido' => $pedido ? [
                'numero' => $pedido->numero,
                'tipo' => match ($pedido->tipo) { Pedido::MESA => 'Mesa', Pedido::DELIVERY => 'Delivery', default => 'Para llevar' },
                'mesa' => $pedido->mesa?->codigo,
                'mozo' => $pedido->usuario?->nombres,
            ] : null,
            'items' => $venta->detalles->map(function ($detalle) use ($opciones): array {
                $producto = $detalle->precio?->producto;
                $presentacion = $detalle->precio?->presentacion?->nombre;
                $opcionesItem = $opciones[(int) $detalle->id_detalle_pedido] ?? null;

                return [
                    // Igual que cbc:Description del XML: el plato con sus opciones.
                    'nombre' => DescripcionItems::describir($producto?->nombre ?? 'Artículo', $opcionesItem),
                    'opciones' => $opcionesItem,
                    'presentacion' => $presentacion ?? 'Unidad base',
                    // Unidad con la que se vendió: la presentación o, si es la base, la unidad del producto.
                    'unidad' => $presentacion && mb_strtolower($presentacion) !== 'unidad base'
                        ? $presentacion
                        : ($producto?->unidadBase?->nombre ?? 'UNIDAD'),
                    'codigo' => $producto?->codigo_barras,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'subtotal' => (float) $detalle->subtotal,
                ];
            })->values()->all(),
            'totales' => $totales,
            'operacion' => [
                'cajero' => $cajero,
                'caja' => $venta->turno?->terminal?->nombre,
            ],
            'leyendas' => $esNota ? [
                'representacion' => 'Documento interno sin valor tributario. Solicite su boleta o factura.',
                'consulta' => null,
            ] : [
                'representacion' => 'Representación impresa de la '.($esFactura ? 'Factura Electrónica' : 'Boleta de Venta Electrónica'),
                'consulta' => 'Consulte su comprobante en www.sunat.gob.pe',
            ],
            'estado_sunat' => $venta->estado_sunat,
            'hash_cpe' => $venta->hash_cpe,
            // El agente genera el QR desde este contenido tributario; no recibe un QR del navegador.
            'qr_fiscal' => $esNota ? null : $this->contenidoQrFiscal($emisor?->ruc_empresa, $documento, $cliente, $totales, $venta->fecha_emision, $venta->hash_cpe),
            'pagos' => $venta->pagos->map(fn ($pago): array => [
                'metodo' => $pago->metodo?->nombre ?? 'No especificado',
                'monto' => (float) $pago->monto,
                'monto_recibido' => $pago->monto_recibido === null ? null : (float) $pago->monto_recibido,
                'vuelto' => $pago->vuelto === null ? null : (float) $pago->vuelto,
                'referencia' => $pago->nro_referencia,
            ])->values()->all(),
        ];
    }

    /** Abreviatura del tipo de documento según el catálogo 06 de SUNAT. */
    public static function abreviaturaDocumento(string $codigoSunat, ?string $nombre): string
    {
        return match ($codigoSunat) {
            '1' => 'DNI',
            '6' => 'RUC',
            '4' => 'C.E.',
            '7' => 'PASAPORTE',
            'A' => 'CED. DIP.',
            default => $nombre ? mb_strtoupper($nombre) : 'DOC.',
        };
    }

    /** Contenido QR fiscal de CPE según los datos ya firmados y aceptados. */
    private function contenidoQrFiscal(?string $ruc, array $documento, array $cliente, array $totales, ?CarbonInterface $fechaEmision, ?string $hash): ?string
    {
        if (blank($ruc) || blank($documento['codigo_sunat']) || blank($documento['serie']) || ! $fechaEmision || blank($hash)) {
            return null;
        }

        return implode('|', [
            $ruc,
            $documento['codigo_sunat'],
            $documento['serie'],
            $documento['correlativo'],
            number_format((float) $totales['igv'], 2, '.', ''),
            number_format((float) $totales['total'], 2, '.', ''),
            $fechaEmision->copy()->setTimezone('America/Lima')->toDateString(),
            $cliente['codigo_sunat'],
            $cliente['numero_documento'],
            $hash,
        ]);
    }
}
