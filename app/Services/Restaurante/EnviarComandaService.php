<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Estacion;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Restaurante\PedidoEvento;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Envía a cocina los ítems pendientes de una cuenta: una comanda por estación.
 *
 * Ruteo por ítem (configurable por sucursal):
 * - Categoría asignada a una estación → esa estación.
 * - Plato PREPARADO sin asignación → estación por defecto, o "Cocina general" si no hay.
 * - Producto DIRECTO (p. ej. gaseosa) sin asignación → no pasa por cocina: queda ENTREGADO.
 *
 * Idempotencia: el envío trae una clave; repetirlo devuelve las mismas comandas y no
 * vuelve a imprimir (la clave de impresión también es única por comanda).
 */
class EnviarComandaService
{
    public function __construct(
        private readonly BloqueoSalonService $bloqueo,
        private readonly ImprimirComandaService $impresion,
        private readonly RegistrarEventoPedido $eventos,
        private readonly ConsumoRecetaService $consumo,
    ) {}

    /**
     * @return array{comandas: Collection<int, Comanda>, directos: int}
     */
    public function enviar(int $pedidoId, string $claveEnvio, int $usuarioId, int $sucursalId): array
    {
        $claveEnvio = trim($claveEnvio);
        if ($claveEnvio === '') {
            throw new DomainException('El envío necesita una clave de idempotencia.');
        }

        return DB::transaction(function () use ($pedidoId, $claveEnvio, $usuarioId, $sucursalId): array {
            $pedido = $this->bloqueo->pedidoAbierto($pedidoId, $sucursalId);
            // Serializa el correlativo y las claves de envío dentro de la sucursal.
            DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->lockForUpdate()->first(['id_sucursal']);

            $envio = PedidoEvento::query()->where('id_pedido', $pedido->id_pedido)
                ->where('tipo', RegistrarEventoPedido::COMANDA_ENVIADA)->where('datos->clave_envio', $claveEnvio)->first();
            $previas = Comanda::query()->where('id_sucursal', $sucursalId)->where('clave_envio', $claveEnvio)->get();
            if ($envio) {
                $confirmadas = $previas->whereIn('numero', $envio->datos['comandas'])->values();
                return ['comandas' => $confirmadas, 'directos' => count($envio->datos['directos'])];
            }
            if ($previas->isNotEmpty()) {
                if ($previas->contains(fn (Comanda $comanda) => (int) $comanda->id_pedido !== $pedidoId)) {
                    throw new DomainException('La clave de envío ya pertenece a otra cuenta.');
                }

                return ['comandas' => $previas, 'directos' => 0];
            }

            $pendientes = $pedido->detalles()
                ->where('estado', PedidoDetalle::PENDIENTE)
                ->with('producto:id_producto,id_categoria,tipo_producto')
                ->lockForUpdate()
                ->orderBy('id_detalle_pedido')
                ->get();
            if ($pendientes->isEmpty()) {
                throw new DomainException('No hay ítems nuevos para enviar a cocina.');
            }

            $this->consumo->descontar($pendientes, $sucursalId, $usuarioId);
            $ruteo = $this->ruteo($sucursalId);
            $ahora = now();
            $grupos = $pendientes->groupBy(fn (PedidoDetalle $detalle) => $this->estacionDe($detalle, $ruteo));

            // Directos sin estación: se sirven sin pasar por cocina.
            $directos = $grupos->pull('directo', collect());
            if ($directos->isNotEmpty()) {
                PedidoDetalle::query()->whereKey($directos->modelKeys())->update(['estado' => PedidoDetalle::ENTREGADO, 'enviado_en' => $ahora]);
            }

            $comandas = collect();
            foreach ($grupos as $claveEstacion => $detalles) {
                $comanda = Comanda::query()->create([
                    'id_sucursal' => $sucursalId,
                    'id_pedido' => $pedido->id_pedido,
                    'id_estacion' => $claveEstacion === 'general' ? null : (int) $claveEstacion,
                    'numero' => $this->siguienteNumero($sucursalId),
                    'estado' => Comanda::PENDIENTE,
                    'clave_envio' => $claveEnvio,
                    'id_usuario' => $usuarioId,
                    'enviada_en' => $ahora,
                ]);
                PedidoDetalle::query()->whereKey($detalles->modelKeys())->update([
                    'estado' => PedidoDetalle::ENVIADO,
                    'id_comanda' => $comanda->id_comanda,
                    'enviado_en' => $ahora,
                ]);
                $this->impresion->comanda($comanda);
                $comandas->push($comanda);
            }

            $pedido->touch();
            $this->eventos->registrar($pedido->id_pedido, $usuarioId, RegistrarEventoPedido::COMANDA_ENVIADA, [
                'clave_envio' => $claveEnvio,
                'comandas' => $comandas->pluck('numero')->all(),
                'items' => $pendientes->count() - $directos->count(),
                'directos' => $directos->pluck('nombre_producto')->all(),
            ]);

            return ['comandas' => $comandas, 'directos' => $directos->count()];
        }, 3);
    }

    /** @return array{categorias: array<int, int>, por_defecto: ?int} */
    private function ruteo(int $sucursalId): array
    {
        $activas = Estacion::query()->where('id_sucursal', $sucursalId)->where('activo', true)->pluck('id_estacion')->map(fn ($id) => (int) $id)->all();

        return [
            'categorias' => DB::table('restaurante.estacion_categorias')
                ->where('id_sucursal', $sucursalId)
                ->whereIn('id_estacion', $activas)
                ->pluck('id_estacion', 'id_categoria')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'por_defecto' => Estacion::query()->where('id_sucursal', $sucursalId)->where('activo', true)->where('por_defecto', true)->value('id_estacion'),
        ];
    }

    /** Clave de agrupación: id de estación, "general" o "directo". */
    private function estacionDe(PedidoDetalle $detalle, array $ruteo): string
    {
        $categoria = $detalle->producto?->id_categoria;
        if ($categoria !== null && isset($ruteo['categorias'][$categoria])) {
            return (string) $ruteo['categorias'][$categoria];
        }
        if ($detalle->tipoRestaurante() === Producto::DIRECTO) {
            return 'directo';
        }

        return $ruteo['por_defecto'] ? (string) $ruteo['por_defecto'] : 'general';
    }

    private function siguienteNumero(int $sucursalId): int
    {
        return (int) Comanda::query()->where('id_sucursal', $sucursalId)->max('numero') + 1;
    }
}
