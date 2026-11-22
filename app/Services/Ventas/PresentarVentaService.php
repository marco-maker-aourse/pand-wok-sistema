<?php

namespace App\Services\Ventas;

use App\Models\TipoComprobante;
use App\Models\Venta;

/**
 * Contrato JSON de una venta para React (historial y respuesta del POS).
 *
 * No depende de nombres internos de relaciones Eloquent: cada clave es parte
 * del contrato con el frontend y no debe renombrarse.
 */
class PresentarVentaService
{
    /** Relaciones necesarias para presentar una venta sin consultas N+1. */
    public const RELACIONES = [
        'cliente:id_cliente,id_tipo_documento,nombre_razon_social,num_documento,direccion_fiscal',
        'cliente.tipoDocumento:id_tipo_documento,codigo_sunat,nombre',
        'comprobante:id_tipo_comprobante,descripcion,codigo_sunat',
        'detalles.precio.producto:id_producto,nombre,codigo_barras,afecta_igv',
        'detalles.precio.presentacion:id_presentacion,nombre',
        'pagos.metodo:id_metodo,nombre,tipo',
    ];

    public function paraHistorial(Venta $venta): array
    {
        return [
            'id_venta' => $venta->id_venta,
            'serie' => $venta->serie,
            'correlativo' => $venta->correlativo,
            'fecha_emision' => $venta->fecha_emision?->toIso8601String(),
            'estado_sunat' => $venta->estado_sunat,
            'hash_cpe' => $venta->hash_cpe,
            'archivos' => [
                'xml' => filled($venta->enlace_xml),
                'cdr' => filled($venta->enlace_cdr),
                'pdf' => filled($venta->enlace_pdf),
            ],
            ...$this->totales($venta),
            'tipo_comprobante' => $venta->comprobante?->codigo_sunat === TipoComprobante::FACTURA ? 'FACTURA' : 'BOLETA',
            'codigo_comprobante' => $venta->comprobante?->codigo_sunat,
            'nombre_razon_social' => $venta->cliente?->nombre_razon_social ?? 'PÚBLICO EN GENERAL',
            'num_documento' => $venta->cliente?->num_documento ?? '00000000',
            'direccion_cliente' => $venta->cliente?->direccion_fiscal,
            'codigo_tipo_documento_cliente' => $venta->cliente?->tipoDocumento?->codigo_sunat ?? '0',
            'detalles' => $this->detalles($venta),
            'pagos' => $this->pagos($venta),
        ];
    }

    /** Respuesta del registro en el POS: incluye el efectivo recibido para el ticket. */
    public function paraRegistro(Venta $venta): array
    {
        $cashPayment = $venta->pagos->first(fn ($payment) => $payment->monto_recibido !== null);

        return [
            'success' => true,
            'id_venta' => $venta->id_venta,
            'serie' => $venta->serie,
            'correlativo' => $venta->correlativo,
            'fecha_emision' => $venta->fecha_emision?->toIso8601String(),
            'codigo_comprobante' => $venta->comprobante?->codigo_sunat,
            'nombre_razon_social' => $venta->cliente?->nombre_razon_social ?? 'PÚBLICO EN GENERAL',
            'num_documento' => $venta->cliente?->num_documento ?? '00000000',
            'codigo_tipo_documento_cliente' => $venta->cliente?->tipoDocumento?->codigo_sunat ?? '0',
            'direccion_cliente' => $venta->cliente?->direccion_fiscal,
            'detalles' => $this->detalles($venta),
            'pagos' => $this->pagos($venta),
            'estado_sunat' => $venta->estado_sunat,
            ...$this->totales($venta),
            'monto_recibido' => $cashPayment?->monto_recibido === null ? null : (float) $cashPayment->monto_recibido,
            'vuelto' => $cashPayment?->vuelto === null ? null : (float) $cashPayment->vuelto,
        ];
    }

    private function totales(Venta $venta): array
    {
        return [
            'op_gravadas' => (float) $venta->op_gravadas,
            'op_exoneradas' => (float) $venta->op_exoneradas,
            'igv' => (float) $venta->igv,
            'descuento_global' => (float) $venta->descuento_global,
            'total_venta' => (float) $venta->total_venta,
        ];
    }

    private function detalles(Venta $venta)
    {
        return $venta->detalles->map(fn ($detalle): array => [
            'producto' => $detalle->precio?->producto?->nombre ?? 'Artículo en venta',
            'codigo' => $detalle->precio?->producto?->codigo_barras,
            'afecta_igv' => (bool) $detalle->precio?->producto?->afecta_igv,
            'presentacion' => $detalle->precio?->presentacion?->nombre,
            'cantidad' => (float) $detalle->cantidad,
            'precio_unitario' => (float) $detalle->precio_unitario,
            'subtotal' => (float) $detalle->subtotal,
        ])->values();
    }

    private function pagos(Venta $venta)
    {
        return $venta->pagos->map(fn ($pago): array => [
            'metodo' => $pago->metodo?->nombre ?? 'No especificado',
            'tipo' => $pago->metodo?->tipo,
            'monto' => (float) $pago->monto,
            'monto_recibido' => $pago->monto_recibido === null ? null : (float) $pago->monto_recibido,
            'vuelto' => $pago->vuelto === null ? null : (float) $pago->vuelto,
        ])->values();
    }
}
