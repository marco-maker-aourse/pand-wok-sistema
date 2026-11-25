<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\CobrarPedidoRequest;
use App\Jobs\EmitirComprobanteSunatJob;
use App\Models\Cliente;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Venta;
use App\Services\Restaurante\CobrarPedidoService;
use App\Services\Ventas\CatalogoPosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CobroPedidoController extends Controller
{
    use ConvierteErroresDeDominio;

    public function show(Request $request, int $pedido, CatalogoPosService $pos): Response
    {
        $usuario = $request->user();
        $sucursal = $usuario->idSucursalActiva();
        $cuenta = Pedido::query()->where('id_sucursal', $sucursal)->with('mesa:id_mesa,codigo')->findOrFail($pedido);
        $cliente = ($cuenta->id_cliente
            ? Cliente::query()->where('estado', true)->find($cuenta->id_cliente)
            : null) ?? $pos->clienteGenerico();
        $cliente = $cliente?->only(['id_cliente', 'nombre_razon_social', 'num_documento']);

        return Inertia::render('Restaurante/CobrarPedido', [
            'pedido' => [
                ...$cuenta->only(['id_pedido', 'numero', 'tipo', 'estado', 'nombre_contacto']),
                'mesa' => $cuenta->mesa?->codigo,
            ],
            'items' => $cuenta->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)
                ->orderBy('id_detalle_pedido')->get(['id_detalle_pedido', 'nombre_producto', 'cantidad', 'cantidad_cobrada', 'precio_unitario']),
            'cobros' => fn () => Venta::query()->where('id_pedido', $pedido)->where('id_sucursal', $sucursal)
                ->latest('id_venta')->get(['id_venta', 'serie', 'correlativo', 'total_venta', 'estado_sunat']),
            'clienteInicial' => $cliente,
            'metodosPago' => $pos->metodosPago(),
            'turno' => $pos->turnoAbierto($usuario->id_usuario, $sucursal),
            'puedeDescontar' => $usuario->hasPermission('pos.descuentos'),
            'puedeVerPedido' => $usuario->hasPermission('pedidos.registrar'),
        ]);
    }

    public function store(CobrarPedidoRequest $request, int $pedido, CobrarPedidoService $servicio): RedirectResponse
    {
        $usuario = $request->user();
        ['venta' => $venta, 'nueva' => $nueva] = $this->enDominio(fn () => $servicio->ejecutar(
            $pedido, $usuario->idSucursalActiva(), $usuario->id_usuario, $request->validated(), $usuario->hasPermission('pos.descuentos'),
        ), 'cobro');

        // La nota de venta no se envía a SUNAT: se imprime de inmediato si la caja imprime sola.
        if ($venta->estado_sunat === Venta::SIN_ENVIO_SUNAT) {
            if ($nueva) {
                app(\App\Services\Impresion\CrearTrabajoImpresionService::class)->comprobanteFiscalAceptado($venta);
            }
        } elseif ($nueva || $venta->estado_sunat === 'PENDIENTE') {
            EmitirComprobanteSunatJob::dispatchAfterResponse($venta->id_venta);
        }

        return to_route('restaurante.cobrar.show', $pedido)->with('success', "Cobro {$venta->serie}-{$venta->correlativo} registrado.");
    }
}
