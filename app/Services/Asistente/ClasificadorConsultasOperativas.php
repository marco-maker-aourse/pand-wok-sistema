<?php

namespace App\Services\Asistente;

use Illuminate\Support\Str;

/** Clasifica frases de negocio antes de consultar datos. */
class ClasificadorConsultasOperativas
{
    public function clasificar(string $consulta): string
    {
        return match (true) {
            preg_match('/^\d{1,3}\s+dias?\s+(antes|atras)$/', $consulta) === 1 => 'seguimiento_documental',
            Str::contains($consulta, ['que puedes hacer', 'que puedes consultar', 'ayuda', 'opciones']) => 'ayuda',
            Str::contains($consulta, ['prediccion', 'proyeccion', 'tendencia', 'como va el negocio', 'analisis de ventas']) => 'analisis',
            Str::contains($consulta, ['que recomiendas', 'prioridades', 'sugerencias', 'que debo revisar']) => 'recomendaciones',
            preg_match('/(producto|articulo).*(vendio|vende|vendido|sale).*mas|(mas).*(vendido|vendio|vende|sale)|top\s+(producto|articulo)/', $consulta) === 1 => 'producto_mas_vendido',
            Str::contains($consulta, ['ultima serie', 'ultimo numero', 'ultimo comprobante', 'ultima factura', 'ultima boleta', 'correlativo']) => 'comprobante',
            preg_match('/(metodos?|formas?)\s+de\s+pagos?/', $consulta) === 1 => 'metodos_pago',
            Str::contains($consulta, ['sin stock', 'agotado', 'quedan existencias']) => 'stock_critico',
            preg_match('/(stock|existencias?)\s+(de|del|para)\s+/', $consulta) === 1 => 'stock_producto',
            Str::contains($consulta, ['turno', 'caja abierta', 'estado de caja', 'arqueo']) => 'caja',
            Str::contains($consulta, ['cobro', 'yape', 'plin', 'efectivo']) => 'cobros',
            Str::contains($consulta, ['cliente', 'clientes']) => 'clientes',
            Str::contains($consulta, ['proveedor', 'proveedores']) => 'proveedores',
            Str::contains($consulta, ['compra', 'compras', 'ingreso de mercaderia']) => 'compras',
            Str::contains($consulta, ['sunat', 'cdr', 'comprobantes pendientes', 'documentos pendientes']) => 'estado_sunat',
            Str::contains($consulta, ['venta', 'vendido', 'facturacion', 'facturado']) => 'ventas',
            Str::contains($consulta, ['producto', 'productos', 'catalogo']) => 'catalogo',
            default => 'abierta',
        };
    }
}
