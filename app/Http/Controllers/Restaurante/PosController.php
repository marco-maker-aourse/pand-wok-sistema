<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\PosRequest;
use App\Jobs\EmitirComprobanteSunatJob;
use App\Models\Cliente;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Pedido;
use App\Services\Restaurante\PresentarRestauranteService;
use App\Services\Restaurante\SugerenciasPosService;
use App\Services\Restaurante\VentaPosService;
use App\Services\Ventas\CatalogoPosService;
use App\Services\Clientes\RegistrarClientePorDocumentoService;
use App\Services\Clientes\RequiereNombreException;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Punto de venta único: carta, ticket y cobro en una pantalla para mostrador,
 * para llevar, delivery y mesas. La lógica vive en VentaPosService.
 */
class PosController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(
        private readonly VentaPosService $pos,
        private readonly PresentarRestauranteService $presentar,
    ) {}

    public function index(Request $request, CatalogoPosService $catalogoPos, SugerenciasPosService $sugerencias): Response|RedirectResponse
    {
        $usuario = $request->user();
        $sucursal = $usuario->idSucursalActiva();

        // Desde el salón: una mesa ocupada abre su cuenta; una libre se preselecciona.
        $mesaInicial = null;
        if ($idMesa = $request->integer('mesa')) {
            $mesa = Mesa::query()->where('id_sucursal', $sucursal)->where('activo', true)->with('pedidoAbierto:id_pedido,id_mesa')->find($idMesa);
            if ($mesa?->pedidoAbierto) {
                return to_route('restaurante.pos', ['cuenta' => $mesa->pedidoAbierto->id_pedido]);
            }
            if ($mesa && $mesa->admiteNuevaCuenta()) {
                $mesaInicial = $mesa->only(['id_mesa', 'codigo', 'capacidad']);
            }
        }

        $cuenta = null;
        if ($id = $request->integer('cuenta')) {
            $registro = Pedido::query()->where('id_sucursal', $sucursal)->find($id);
            $cuenta = $registro?->estaAbierto() ? $this->presentar->pedido($registro) : null;
        }

        return Inertia::render('Restaurante/Pos', [
            'carta' => $this->presentar->cartaPedido($sucursal),
            // Sugerencias deterministas con pedidos reales de la sucursal.
            'sugerencias' => fn () => $sugerencias->calcular($sucursal, $usuario->id_usuario),
            'salon' => fn () => $this->presentar->salon($sucursal),
            'cuenta' => $cuenta,
            'inicio' => [
                'mesa' => $mesaInicial,
                'tipo' => $mesaInicial ? Pedido::MESA : (in_array($request->query('tipo'), Pedido::TIPOS, true) ? $request->query('tipo') : Pedido::PARA_LLEVAR),
            ],
            'metodosPago' => $catalogoPos->metodosPago(),
            'turno' => $catalogoPos->turnoAbierto($usuario->id_usuario, $sucursal),
            'clienteInicial' => Cliente::query()->where('estado', true)->where('num_documento', '00000000')
                ->first(['id_cliente', 'nombre_razon_social', 'num_documento']),
            'puede' => [
                'enviar' => $usuario->hasPermission('comandas.enviar'),
                'cobrar' => $usuario->hasPermission('ventas.registrar'),
                'descontar' => $usuario->hasPermission('pos.descuentos'),
                'anular_enviados' => $usuario->hasPermission('pedidos.anular'),
                'transferir' => $usuario->hasPermission('pedidos.transferir'),
            ],
            'ultimaVenta' => fn () => $request->session()->get('pos.ultima_venta'),
        ]);
    }

    /**
     * Cliente del comprobante por DNI/RUC: lo busca, lo consulta en la API (API Perú) y
     * lo registra; si la API no está disponible, pide el nombre (requiere_nombre).
     */
    public function cliente(Request $request, RegistrarClientePorDocumentoService $clientes): JsonResponse
    {
        $datos = $request->validate([
            'documento' => ['required', 'string', 'regex:/^(\d{8}|\d{11})$/'],
            'nombre' => ['nullable', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ], ['documento.regex' => 'Escribe un DNI de 8 dígitos o un RUC de 11.']);

        try {
            ['cliente' => $cliente, 'origen' => $origen] = $clientes->ejecutar($datos['documento'], $datos['nombre'] ?? null, $datos['direccion'] ?? null);
        } catch (RequiereNombreException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'requiere_nombre' => true], 422);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'origen' => $origen,
            'cliente' => $cliente->only(['id_cliente', 'nombre_razon_social', 'num_documento', 'direccion_fiscal']),
        ]);
    }

    public function enviar(PosRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        ['pedido' => $pedido, 'comandas' => $comandas] = $this->enDominio(fn () => $this->pos->enviar(
            $request->filled('id_pedido') ? $request->integer('id_pedido') : null,
            $request->datos(),
            $usuario->id_usuario,
            $usuario->idSucursalActiva(),
        ), 'pos');

        return to_route('restaurante.pos', ['cuenta' => $pedido->id_pedido])
            ->with('success', $comandas ? "Enviado a cocina ({$comandas} comanda".($comandas === 1 ? '' : 's').').' : 'Cuenta guardada.');
    }

    public function cobrar(PosRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        ['pedido' => $pedido, 'venta' => $venta, 'nueva' => $nueva] = $this->enDominio(fn () => $this->pos->cobrar(
            $request->filled('id_pedido') ? $request->integer('id_pedido') : null,
            $request->datos(),
            $usuario->id_usuario,
            $usuario->idSucursalActiva(),
            $usuario->hasPermission('pos.descuentos'),
        ), 'pos');

        if ($nueva || $venta->estado_sunat === 'PENDIENTE') {
            EmitirComprobanteSunatJob::dispatchAfterResponse($venta->id_venta);
        }

        $recibido = collect($request->validated('pagos'))->sum(fn (array $pago) => (float) ($pago['monto_recibido'] ?? $pago['monto']));
        $request->session()->flash('pos.ultima_venta', [
            'id_venta' => $venta->id_venta,
            'comprobante' => "{$venta->serie}-".str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
            'total' => (float) $venta->total_venta,
            'vuelto' => max(0, round($recibido - (float) $venta->total_venta, 2)),
            'cuenta' => $pedido->numero,
        ]);

        // Una cuenta de mesa con saldo (cobro parcial no aplica aquí) sigue abierta; si se cerró, ticket nuevo.
        return $pedido->refresh()->estaAbierto()
            ? to_route('restaurante.pos', ['cuenta' => $pedido->id_pedido])
            : to_route('restaurante.pos');
    }
}
