<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\AbrirPedidoRequest;
use App\Http\Requests\Restaurante\ActualizarItemPedidoRequest;
use App\Http\Requests\Restaurante\AgregarItemsPedidoRequest;
use App\Http\Requests\Restaurante\AnularRequest;
use App\Http\Requests\Restaurante\EnviarComandaRequest;
use App\Http\Requests\Restaurante\TransferirPedidoRequest;
use App\Models\Restaurante\Pedido;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\PresentarRestauranteService;
use App\Services\Restaurante\TransferirPedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Toma de pedidos. La sucursal y el usuario salen de la sesión; los servicios
 * validan que el pedido pertenezca a esa sucursal.
 */
class PedidoController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(
        private readonly GestionarPedidoService $pedidos,
        private readonly TransferirPedidoService $transferencias,
        private readonly PresentarRestauranteService $presentar,
        private readonly EnviarComandaService $envios,
    ) {}

    public function store(AbrirPedidoRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        $pedido = $this->enDominio(fn () => $this->pedidos->abrir($request->validated(), $usuario->id_usuario, $usuario->idSucursalActiva()));

        return to_route('restaurante.pedidos.show', $pedido->id_pedido);
    }

    public function show(Request $request, int $pedido): Response|RedirectResponse
    {
        $usuario = $request->user();
        $registro = Pedido::query()->where('id_sucursal', $usuario->idSucursalActiva())->findOrFail($pedido);

        // Una cuenta unida a otra continúa en la cuenta destino.
        if ($registro->estado === Pedido::UNIDO && $registro->id_pedido_destino) {
            return to_route('restaurante.pedidos.show', $registro->id_pedido_destino)
                ->with('success', "La cuenta #{$registro->numero} se unió a esta cuenta.");
        }

        $abierto = $registro->estaAbierto();

        return Inertia::render('Restaurante/Pedido', [
            'pedido' => $this->presentar->pedido($registro),
            'carta' => $abierto ? $this->presentar->cartaPedido($registro->id_sucursal) : null,
            'destinos' => $abierto && $usuario->hasPermission('pedidos.transferir')
                ? $this->presentar->destinosTransferencia($registro)
                : null,
            'puede' => [
                'editar' => $abierto,
                'cobrar' => $usuario->hasPermission('ventas.registrar'),
                'enviar' => $abierto && $usuario->hasPermission('comandas.enviar'),
                'reimprimir' => $usuario->hasPermission('comandas.enviar') || $usuario->hasPermission('cocina.gestionar'),
                'anular_enviados' => $usuario->hasPermission('pedidos.anular'),
                'transferir' => $usuario->hasPermission('pedidos.transferir'),
            ],
        ]);
    }

    public function agregarItems(AgregarItemsPedidoRequest $request, int $pedido): RedirectResponse
    {
        $usuario = $request->user();
        $items = $this->enDominio(fn () => $this->pedidos->agregarItems($pedido, $request->items(), $usuario->id_usuario, $usuario->idSucursalActiva()), 'items');

        return back()->with('success', count($items) === 1 ? 'Ítem agregado a la cuenta.' : count($items).' ítems agregados a la cuenta.');
    }

    public function enviar(EnviarComandaRequest $request, int $pedido): RedirectResponse
    {
        $usuario = $request->user();
        $sucursal = $usuario->idSucursalActiva();
        Pedido::query()->where('id_sucursal', $sucursal)->findOrFail($pedido);

        $resultado = $this->enDominio(fn () => $this->envios->enviar(
            $pedido, $request->validated('clave_envio'), $usuario->id_usuario, $sucursal,
        ));

        return $this->conAvisoDeImpresion(back(), $resultado);
    }

    public function guardarYEnviar(AgregarItemsPedidoRequest $request, int $pedido): RedirectResponse
    {
        $usuario = $request->user();
        $sucursal = $usuario->idSucursalActiva();
        Pedido::query()->where('id_sucursal', $sucursal)->findOrFail($pedido);

        $resultado = $this->enDominio(fn () => DB::transaction(function () use ($pedido, $usuario, $sucursal, $request): array {
            $this->pedidos->agregarItems($pedido, $request->items(), $usuario->id_usuario, $sucursal);

            return $this->envios->enviar($pedido, $request->validated('clave_envio'), $usuario->id_usuario, $sucursal);
        }, 3), 'items');

        return $this->conAvisoDeImpresion(back(), $resultado);
    }

    private function conAvisoDeImpresion(RedirectResponse $respuesta, array $resultado): RedirectResponse
    {
        $respuesta->with('success', $this->mensajeEnvio($resultado));
        if ($aviso = EnviarComandaService::avisoSinImprimir($resultado['sin_imprimir'] ?? [])) {
            $respuesta->with('warning', $aviso);
        }

        return $respuesta;
    }

    private function mensajeEnvio(array $resultado): string
    {
        $comandas = $resultado['comandas']->count();
        $directos = $resultado['directos'];

        return collect([
            $comandas ? "Comandas enviadas: {$comandas}." : null,
            $directos ? "Ítems de entrega directa: {$directos}." : null,
        ])->filter()->implode(' ') ?: 'El envío ya estaba registrado.';
    }

    public function actualizarItem(ActualizarItemPedidoRequest $request, int $detalle): RedirectResponse
    {
        $usuario = $request->user();
        $this->enDominio(fn () => $this->pedidos->actualizarItem($detalle, $request->validated(), $usuario->id_usuario, $usuario->idSucursalActiva()));

        return back();
    }

    public function anularItem(AnularRequest $request, int $detalle): RedirectResponse
    {
        $usuario = $request->user();
        $anulado = $this->enDominio(fn () => $this->pedidos->anularItem(
            $detalle,
            $request->validated('motivo'),
            $usuario->hasPermission('pedidos.anular'),
            $usuario->id_usuario,
            $usuario->idSucursalActiva(),
            $request->boolean('recuperar_insumos'),
        ), 'motivo');

        return back()->with('success', "{$anulado->nombre_producto} se quitó de la cuenta.");
    }

    public function anular(AnularRequest $request, int $pedido): RedirectResponse
    {
        $usuario = $request->user();
        $anulado = $this->enDominio(fn () => $this->pedidos->anularPedido(
            $pedido,
            $request->validated('motivo'),
            $usuario->hasPermission('pedidos.anular'),
            $usuario->id_usuario,
            $usuario->idSucursalActiva(),
            $request->boolean('recuperar_insumos'),
        ), 'motivo');

        return to_route('restaurante.salon')->with('success', "Cuenta #{$anulado->numero} anulada.");
    }

    public function transferir(TransferirPedidoRequest $request, int $pedido): RedirectResponse
    {
        $usuario = $request->user();
        $datos = $request->validated();
        $sucursal = $usuario->idSucursalActiva();

        $destino = $this->enDominio(fn () => match ($datos['operacion']) {
            'mesa' => $this->transferencias->cambiarMesa($pedido, (int) $datos['id_mesa'], $usuario->id_usuario, $sucursal),
            'unir' => $this->transferencias->unir($pedido, (int) $datos['id_pedido_destino'], $usuario->id_usuario, $sucursal),
            'mover' => $this->transferencias->moverItems($pedido, (int) $datos['id_pedido_destino'], $datos['detalles'] ?? [], $usuario->id_usuario, $sucursal),
        });

        $mensaje = match ($datos['operacion']) {
            'mesa' => 'La cuenta cambió de mesa.',
            'unir' => "Cuentas unidas en la cuenta #{$destino->numero}.",
            'mover' => "Ítems movidos a la cuenta #{$destino->numero}.",
        };

        // Al unir, la cuenta actual deja de existir como tal: se continúa en la destino.
        return $datos['operacion'] === 'unir'
            ? to_route('restaurante.pedidos.show', $destino->id_pedido)->with('success', $mensaje)
            : back()->with('success', $mensaje);
    }
}
