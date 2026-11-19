<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\Comprobantes\PresentarComprobanteService;
use App\Services\Impresion\CrearTrabajoImpresionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTermicoController extends Controller
{
    /**
     * Impresión manual del ticket fiscal. Responde JSON para que la pantalla muestre el
     * resultado real: antes una redirección hacía que axios informara éxito aunque fallara.
     */
    public function imprimir(Request $request, Venta $venta, CrearTrabajoImpresionService $impresion): JsonResponse
    {
        abort_unless((int) $venta->id_sucursal === $request->user()->idSucursalActiva(), 404);
        $data = $request->validate(['solicitud' => ['nullable', 'uuid']]);

        if ($venta->estado_sunat !== 'ACEPTADO') {
            return response()->json(['message' => 'El ticket se puede imprimir cuando SUNAT acepte el comprobante.'], 422);
        }

        // Se registra solo el efecto de impresión; nunca se recrea una venta, pago o CPE.
        $trabajo = $impresion->reimprimirComprobanteFiscal($venta, $data['solicitud'] ?? null);

        if (! $trabajo) {
            return response()->json(['message' => 'La caja de esta venta no tiene una terminal e impresora activas.'], 422);
        }

        return response()->json([
            'message' => 'Ticket enviado a la impresora de la caja.',
            'id_trabajo_impresion' => $trabajo->id_trabajo_impresion,
        ], 201);
    }

    /**
     * Representación del comprobante para la vista previa de Ventas: el mismo contrato que
     * recibe el agente de impresión (y el PDF), sin cálculos en el navegador. Solo lectura:
     * no crea trabajos, no toca la venta ni el estado SUNAT.
     */
    public function representacion(Request $request, Venta $venta, PresentarComprobanteService $presentacion, CrearTrabajoImpresionService $impresion): JsonResponse
    {
        abort_unless((int) $venta->id_sucursal === $request->user()->idSucursalActiva(), 404);

        $venta->loadMissing('turno.terminal.impresora');

        return response()->json([
            'comprobante' => $presentacion->paraVenta($venta),
            // Ancho del papel de la impresora de esa caja, para previsualizar lo que saldrá impreso.
            'ancho_papel_mm' => $impresion->anchoPapel($venta),
        ]);
    }
}
