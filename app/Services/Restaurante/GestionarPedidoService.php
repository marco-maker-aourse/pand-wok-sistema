<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Restaurante\PedidoDetalleModificador;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una cuenta antes del cobro: abrir, tomar ítems, corregirlos y anular.
 *
 * Idempotencia: abrir y agregar reciben una clave generada por el navegador. Repetir la
 * misma petición (doble toque, reintento por red) devuelve lo ya registrado sin duplicar.
 */
class GestionarPedidoService
{
    public function __construct(
        private readonly BloqueoSalonService $bloqueo,
        private readonly ArmarItemPedidoService $items,
        private readonly RegistrarEventoPedido $eventos,
        private readonly CocinaService $cocina,
        private readonly ConsumoRecetaService $consumo,
        private readonly CerrarCuentaSaldada $cierre,
    ) {}

    /**
     * @param  array{tipo:string, id_mesa?:?int, comensales?:?int, id_cliente?:?int, nombre_contacto?:?string,
     *               telefono_contacto?:?string, direccion_entrega?:?string, observacion?:?string, clave_idempotencia:string}  $datos
     */
    public function abrir(array $datos, int $usuarioId, int $sucursalId): Pedido
    {
        $clave = (string) $datos['clave_idempotencia'];
        if ($existente = $this->pedidoPorClave($clave, $sucursalId)) {
            return $existente;
        }

        try {
            return DB::transaction(fn (): Pedido => $this->crearPedido($datos, $clave, $usuarioId, $sucursalId));
        } catch (UniqueConstraintViolationException $exception) {
            // Dos peticiones simultáneas con la misma clave: la otra ya lo creó.
            if ($existente = $this->pedidoPorClave($clave, $sucursalId)) {
                return $existente;
            }
            // Otra cuenta ganó la mesa en paralelo (índice único parcial).
            throw new DomainException('La mesa acaba de recibir otra cuenta. Actualiza el salón.', previous: $exception);
        }
    }

    /**
     * Agrega ítems PENDIENTES (aún no enviados a cocina). Cada ítem trae su propia clave:
     * una línea ya registrada se devuelve tal cual en lugar de duplicarse.
     *
     * @param  array<int, array{clave:string, id_precio:int, cantidad:float|int|string, observacion?:?string, opciones?:array<int>}>  $items
     * @return array<int, PedidoDetalle>
     */
    public function agregarItems(int $pedidoId, array $items, int $usuarioId, int $sucursalId): array
    {
        if ($items === []) {
            throw new DomainException('Agrega al menos un producto.');
        }

        return DB::transaction(function () use ($pedidoId, $items, $usuarioId, $sucursalId): array {
            $pedido = $this->bloqueo->pedidoAbierto($pedidoId, $sucursalId);
            $registrados = [];

            foreach ($items as $item) {
                $clave = (string) ($item['clave'] ?? '');
                if ($clave === '') {
                    throw new DomainException('Cada ítem necesita una clave de idempotencia.');
                }

                $previo = PedidoDetalle::query()->where('clave_idempotencia', $clave)->first();
                if ($previo) {
                    if ((int) $previo->id_pedido !== (int) $pedido->id_pedido) {
                        throw new DomainException('La clave de un ítem ya pertenece a otro pedido.');
                    }
                    $registrados[] = $previo;

                    continue;
                }

                ['detalle' => $detalle, 'modificadores' => $modificadores] = $this->items->armar($item, $sucursalId);

                $nuevo = PedidoDetalle::query()->create([
                    ...$detalle,
                    'id_pedido' => $pedido->id_pedido,
                    'estado' => PedidoDetalle::PENDIENTE,
                    'id_usuario' => $usuarioId,
                    'clave_idempotencia' => $clave,
                ]);

                foreach ($modificadores as $modificador) {
                    PedidoDetalleModificador::query()->create([...$modificador, 'id_detalle_pedido' => $nuevo->id_detalle_pedido]);
                }

                $this->eventos->registrar($pedido->id_pedido, $usuarioId, RegistrarEventoPedido::ITEM_AGREGADO, [
                    'producto' => $nuevo->nombre_producto,
                    'cantidad' => (float) $nuevo->cantidad,
                    'precio_unitario' => (float) $nuevo->precio_unitario,
                    'opciones' => array_column($modificadores, 'nombre_opcion'),
                ], $nuevo->id_detalle_pedido);

                $registrados[] = $nuevo;
            }

            $pedido->touch();

            return $registrados;
        });
    }

    /** Cambia cantidad u observación de un ítem que todavía no salió a cocina. */
    public function actualizarItem(int $detalleId, array $cambios, int $usuarioId, int $sucursalId): PedidoDetalle
    {
        return DB::transaction(function () use ($detalleId, $cambios, $usuarioId, $sucursalId): PedidoDetalle {
            $detalle = $this->detalleBloqueado($detalleId, $sucursalId);
            if (! $detalle->estaPendiente() || $detalle->tieneCobro()) {
                throw new DomainException('Solo se pueden editar ítems pendientes y sin cobros.');
            }

            $antes = ['cantidad' => (float) $detalle->cantidad, 'observacion' => $detalle->observacion];
            $nuevos = [];

            if (array_key_exists('cantidad', $cambios)) {
                $cantidad = round((float) $cambios['cantidad'], 3);
                if ($cantidad <= 0 || $cantidad > ArmarItemPedidoService::CANTIDAD_MAXIMA) {
                    throw new DomainException('La cantidad debe ser mayor que cero y no superar '.ArmarItemPedidoService::CANTIDAD_MAXIMA.'.');
                }
                $nuevos['cantidad'] = $cantidad;
                $nuevos['subtotal'] = ArmarItemPedidoService::subtotal($cantidad, (float) $detalle->precio_unitario);
            }
            if (array_key_exists('observacion', $cambios)) {
                $nuevos['observacion'] = ArmarItemPedidoService::textoOpcional($cambios['observacion']);
            }
            if ($nuevos === []) {
                return $detalle;
            }

            $detalle->update($nuevos);
            $this->eventos->registrar($detalle->id_pedido, $usuarioId, RegistrarEventoPedido::ITEM_MODIFICADO, [
                'producto' => $detalle->nombre_producto,
                'antes' => $antes,
                'despues' => ['cantidad' => (float) $detalle->cantidad, 'observacion' => $detalle->observacion],
            ], $detalle->id_detalle_pedido);

            return $detalle->refresh();
        });
    }

    /**
     * Quita un ítem. Si aún no salió a cocina basta con pedido abierto; si ya salió,
     * exige el permiso pedidos.anular y un motivo, porque cocina pudo haberlo preparado.
     */
    public function anularItem(int $detalleId, ?string $motivo, bool $puedeAnularEnviados, int $usuarioId, int $sucursalId, bool $recuperar = false): PedidoDetalle
    {
        return DB::transaction(function () use ($detalleId, $motivo, $puedeAnularEnviados, $usuarioId, $sucursalId, $recuperar): PedidoDetalle {
            $detalle = $this->detalleBloqueado($detalleId, $sucursalId);
            if ($detalle->estaAnulado()) {
                return $detalle;
            }

            $motivo = ArmarItemPedidoService::textoOpcional($motivo);
            $this->validarAnulacion($detalle, $motivo, $puedeAnularEnviados);

            $this->consumo->resolverAnulacion(collect([$detalle]), $sucursalId, $usuarioId, $recuperar);
            $estadoPrevio = $detalle->estado;
            $detalle->update([
                'estado' => PedidoDetalle::ANULADO,
                'motivo_anulacion' => $motivo,
                'id_usuario_anulacion' => $usuarioId,
                'anulado_en' => now(),
            ]);
            // Si ya estaba en cocina, la estación recibe el aviso y la comanda se actualiza.
            $this->cocina->itemsAnulados(collect([$detalle]));

            $this->eventos->registrar($detalle->id_pedido, $usuarioId, RegistrarEventoPedido::ITEM_ANULADO, [
                'producto' => $detalle->nombre_producto,
                'cantidad' => (float) $detalle->cantidad,
                'estado_previo' => $estadoPrevio,
                'motivo' => $motivo,
            ], $detalle->id_detalle_pedido);
            $this->cierre->ejecutar($detalle->pedido, $usuarioId);

            return $detalle->refresh();
        }, 3);
    }

    /** Anula la cuenta completa (p. ej. el cliente se retiró) y libera la mesa. */
    public function anularPedido(int $pedidoId, ?string $motivo, bool $puedeAnularEnviados, int $usuarioId, int $sucursalId, bool $recuperar = false): Pedido
    {
        $motivo = ArmarItemPedidoService::textoOpcional($motivo);
        if ($motivo === null) {
            throw new DomainException('Indica el motivo de la anulación.');
        }

        return DB::transaction(function () use ($pedidoId, $motivo, $puedeAnularEnviados, $usuarioId, $sucursalId, $recuperar): Pedido {
            $pedido = $this->bloqueo->pedidoAbierto($pedidoId, $sucursalId);
            $activos = $pedido->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)->lockForUpdate()->get();

            foreach ($activos as $detalle) {
                $this->validarAnulacion($detalle, $motivo, $puedeAnularEnviados);
            }

            $this->consumo->resolverAnulacion($activos, $sucursalId, $usuarioId, $recuperar);
            PedidoDetalle::query()->whereKey($activos->modelKeys())->update([
                'estado' => PedidoDetalle::ANULADO,
                'motivo_anulacion' => $motivo,
                'id_usuario_anulacion' => $usuarioId,
                'anulado_en' => now(),
            ]);
            $this->cocina->itemsAnulados($activos);

            $pedido->update([
                'estado' => Pedido::ANULADO,
                'motivo_anulacion' => $motivo,
                'cerrado_en' => now(),
                'id_usuario_cierre' => $usuarioId,
            ]);
            $this->bloqueo->liberarMesa($pedido->id_mesa, $sucursalId);

            $this->eventos->registrar($pedido->id_pedido, $usuarioId, RegistrarEventoPedido::PEDIDO_ANULADO, [
                'motivo' => $motivo,
                'items_anulados' => $activos->count(),
            ]);

            return $pedido->refresh();
        }, 3);
    }

    /** Total vigente de la cuenta (sin ítems anulados). El IGV se calcula al emitir la venta. */
    public function total(Pedido $pedido): float
    {
        return round((float) $pedido->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)->sum('subtotal'), 2);
    }

    private function crearPedido(array $datos, string $clave, int $usuarioId, int $sucursalId): Pedido
    {
        $tipo = (string) $datos['tipo'];
        if (! in_array($tipo, Pedido::TIPOS, true)) {
            throw new DomainException('Tipo de pedido no válido.');
        }

        $mesaId = null;
        if ($tipo === Pedido::MESA) {
            $mesa = $this->bloqueo->mesa((int) ($datos['id_mesa'] ?? 0), $sucursalId);
            $this->bloqueo->ocuparMesa($mesa);
            $mesaId = $mesa->id_mesa;
        } elseif ($tipo === Pedido::DELIVERY && ArmarItemPedidoService::textoOpcional($datos['direccion_entrega'] ?? null) === null) {
            throw new DomainException('El delivery necesita una dirección de entrega.');
        }

        $pedido = Pedido::query()->create([
            'id_sucursal' => $sucursalId,
            'numero' => $this->siguienteNumero($sucursalId),
            'tipo' => $tipo,
            'id_mesa' => $mesaId,
            'id_usuario' => $usuarioId,
            'id_cliente' => $datos['id_cliente'] ?? null,
            'comensales' => $datos['comensales'] ?? null,
            'nombre_contacto' => ArmarItemPedidoService::textoOpcional($datos['nombre_contacto'] ?? null),
            'telefono_contacto' => ArmarItemPedidoService::textoOpcional($datos['telefono_contacto'] ?? null),
            'direccion_entrega' => ArmarItemPedidoService::textoOpcional($datos['direccion_entrega'] ?? null),
            'observacion' => ArmarItemPedidoService::textoOpcional($datos['observacion'] ?? null),
            'estado' => Pedido::ABIERTO,
            'clave_idempotencia' => $clave,
            'abierto_en' => now(),
        ]);

        $this->eventos->registrar($pedido->id_pedido, $usuarioId, RegistrarEventoPedido::PEDIDO_ABIERTO, [
            'tipo' => $tipo,
            'id_mesa' => $mesaId,
            'comensales' => $pedido->comensales,
        ]);

        return $pedido;
    }

    /** Correlativo por sucursal. El bloqueo de la fila de la sucursal serializa las aperturas. */
    private function siguienteNumero(int $sucursalId): int
    {
        $sucursal = DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->lockForUpdate()->first(['id_sucursal']);
        if (! $sucursal) {
            throw new DomainException('La sucursal no existe.');
        }

        return (int) Pedido::query()->where('id_sucursal', $sucursalId)->max('numero') + 1;
    }

    private function pedidoPorClave(string $clave, int $sucursalId): ?Pedido
    {
        $pedido = Pedido::query()->where('clave_idempotencia', $clave)->first();
        if ($pedido && (int) $pedido->id_sucursal !== $sucursalId) {
            throw new DomainException('La clave de la operación ya pertenece a otra sucursal.');
        }

        return $pedido;
    }

    private function detalleBloqueado(int $detalleId, int $sucursalId): PedidoDetalle
    {
        $pedidoId = PedidoDetalle::query()->whereKey($detalleId)
            ->whereHas('pedido', fn ($query) => $query->where('id_sucursal', $sucursalId))->value('id_pedido');
        if (! $pedidoId) {
            throw new DomainException('El ítem no existe.');
        }
        $pedido = $this->bloqueo->pedidoAbierto($pedidoId, $sucursalId);
        $detalle = $pedido->detalles()->lockForUpdate()->find($detalleId);
        if (! $detalle) {
            throw new DomainException('El ítem ya no pertenece a esta cuenta.');
        }

        return $detalle->setRelation('pedido', $pedido);
    }

    private function validarAnulacion(PedidoDetalle $detalle, ?string $motivo, bool $puedeAnularEnviados): void
    {
        if ($detalle->tieneCobro()) {
            throw new DomainException("{$detalle->nombre_producto} ya tiene un cobro registrado y no puede anularse.");
        }
        if ($detalle->estaPendiente()) {
            return;
        }
        if (! $puedeAnularEnviados) {
            throw new DomainException("{$detalle->nombre_producto} ya se envió a cocina; solo un usuario autorizado puede anularlo.");
        }
        if ($motivo === null) {
            throw new DomainException("Indica el motivo para anular {$detalle->nombre_producto}, que ya se envió a cocina.");
        }
    }
}
