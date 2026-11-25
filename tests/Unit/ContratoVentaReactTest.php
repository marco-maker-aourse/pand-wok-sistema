<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\MetodoPago;
use App\Models\PagoVenta;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TipoComprobante;
use App\Models\Venta;
use App\Services\Ventas\PresentarVentaService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/** Fija las claves que consumen Ventas/Index y el POS; renombrarlas rompe React. */
class ContratoVentaReactTest extends TestCase
{
    public function test_historial_conserva_las_claves_y_valores_del_contrato(): void
    {
        $datos = app(PresentarVentaService::class)->paraHistorial($this->venta());

        $this->assertSame([
            'id_venta', 'serie', 'correlativo', 'fecha_emision', 'estado_sunat',
            'mensaje_sunat', 'codigo_respuesta_sunat', 'intentos_sunat', 'ultimo_intento_sunat',
            'hash_cpe', 'archivos',
            'op_gravadas', 'op_exoneradas', 'igv', 'descuento_global', 'total_venta',
            'tipo_comprobante', 'codigo_comprobante', 'nombre_razon_social', 'num_documento',
            'direccion_cliente', 'codigo_tipo_documento_cliente', 'detalles', 'pagos',
        ], array_keys($datos));
        $this->assertSame('FACTURA', $datos['tipo_comprobante']);
        $this->assertSame('Comprobante aceptado', $datos['mensaje_sunat']);
        $this->assertSame('0', $datos['codigo_respuesta_sunat']);
        $this->assertSame(2, $datos['intentos_sunat']);
        $this->assertNull($datos['ultimo_intento_sunat']);
        $this->assertSame(['xml' => true, 'cdr' => false, 'pdf' => false], $datos['archivos']);
        $this->assertSame(['producto', 'codigo', 'afecta_igv', 'presentacion', 'cantidad', 'precio_unitario', 'subtotal'], array_keys($datos['detalles'][0]));
        $this->assertSame(['metodo', 'tipo', 'monto', 'monto_recibido', 'vuelto'], array_keys($datos['pagos'][0]));
        $this->assertSame(23.6, $datos['total_venta']);
    }

    public function test_registro_incluye_efectivo_recibido_y_vuelto(): void
    {
        $datos = app(PresentarVentaService::class)->paraRegistro($this->venta());

        $this->assertSame([
            'success', 'id_venta', 'serie', 'correlativo', 'fecha_emision', 'codigo_comprobante',
            'nombre_razon_social', 'num_documento', 'codigo_tipo_documento_cliente', 'direccion_cliente',
            'detalles', 'pagos', 'estado_sunat', 'op_gravadas', 'op_exoneradas', 'igv',
            'descuento_global', 'total_venta', 'monto_recibido', 'vuelto',
        ], array_keys($datos));
        $this->assertSame(50.0, $datos['monto_recibido']);
        $this->assertSame(26.4, $datos['vuelto']);
    }

    public function test_venta_sin_cliente_usa_publico_en_general(): void
    {
        $venta = $this->venta();
        $venta->setRelation('cliente', null);

        $datos = app(PresentarVentaService::class)->paraHistorial($venta);

        $this->assertSame('PÚBLICO EN GENERAL', $datos['nombre_razon_social']);
        $this->assertSame('00000000', $datos['num_documento']);
        $this->assertSame('0', $datos['codigo_tipo_documento_cliente']);
    }

    private function venta(): Venta
    {
        $venta = (new Venta)->forceFill([
            'id_venta' => 7, 'serie' => 'F001', 'correlativo' => 15, 'estado_sunat' => 'ACEPTADO',
            'mensaje_sunat' => 'Comprobante aceptado', 'codigo_respuesta_sunat' => '0', 'intentos_sunat' => 2,
            'op_gravadas' => 20, 'op_exoneradas' => 0, 'igv' => 3.6, 'descuento_global' => 0,
            'total_venta' => 23.6, 'enlace_xml' => 'sunat/xml/F001-15.xml',
        ]);
        $producto = (new Producto)->forceFill(['nombre' => 'ARROZ', 'codigo_barras' => '775', 'afecta_igv' => true]);
        $precio = (new ProductoPresentacion)->setRelation('producto', $producto)->setRelation('presentacion', null);
        $detalle = (new DetalleVenta)->forceFill(['cantidad' => 2, 'precio_unitario' => 11.8, 'subtotal' => 23.6])->setRelation('precio', $precio);
        $pago = (new PagoVenta)->forceFill(['monto' => 23.6, 'monto_recibido' => 50, 'vuelto' => 26.4])
            ->setRelation('metodo', (new MetodoPago)->forceFill(['nombre' => 'EFECTIVO', 'tipo' => 'EFECTIVO']));

        return $venta
            ->setRelation('cliente', (new Cliente)->forceFill(['nombre_razon_social' => 'EMPRESA SAC', 'num_documento' => '20123456789'])->setRelation('tipoDocumento', null))
            ->setRelation('comprobante', (new TipoComprobante)->forceFill(['codigo_sunat' => '01']))
            ->setRelation('detalles', new Collection([$detalle]))
            ->setRelation('pagos', new Collection([$pago]));
    }
}
