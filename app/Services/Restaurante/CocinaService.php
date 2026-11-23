<?php

namespace App\Services\Restaurante;

use App\Support\Restaurante\DistintivoCategoria;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Estacion;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\TrabajoImpresion;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla de cocina (KDS) y ciclo de vida de las comandas.
 *
 * Estados: PENDIENTE → EN_PREPARACION → LISTA → ENTREGADA. Se puede marcar LISTA sin pasar
 * por preparación y devolver una LISTA a preparación (corrección). Los ítems vigentes de la
 * comanda siguen su estado; los anulados no cambian. Cada transición guarda su hora, que
 * alimenta los reportes de tiempos de preparación.
 */
class CocinaService
{
    private const TRANSICIONES = [
        Comanda::PENDIENTE => [Comanda::EN_PREPARACION, Comanda::LISTA],
        Comanda::EN_PREPARACION => [Comanda::LISTA],
        Comanda::LISTA => [Comanda::ENTREGADA, Comanda::EN_PREPARACION],
    ];

    private const ESTADO_ITEM = [
        Comanda::EN_PREPARACION => PedidoDetalle::EN_PREPARACION,
        Comanda::LISTA => PedidoDetalle::LISTO,
        Comanda::ENTREGADA => PedidoDetalle::ENTREGADO,
    ];

    private const MARCA_TIEMPO = [
        Comanda::EN_PREPARACION => 'iniciada_en',
        Comanda::LISTA => 'lista_en',
        Comanda::ENTREGADA => 'entregada_en',
    ];

    public function __construct(private readonly ImprimirComandaService $impresion) {}

    /**
     * Comandas activas de la sucursal (o de una estación) con sus ítems.
     * $estacion: null = todas; 0 = solo "Cocina general".
     */
    public function tablero(int $sucursalId, ?int $estacion = null): array
    {
        $comandas = Comanda::query()
            ->where('id_sucursal', $sucursalId)
            ->when($estacion === 0, fn ($query) => $query->whereNull('id_estacion'))
            ->when($estacion, fn ($query, int $id) => $query->where('id_estacion', $id))
            ->with([
                'pedido:id_pedido,numero,tipo,id_mesa,nombre_contacto,comensales,observacion',
                'pedido.mesa:id_mesa,codigo',
                'estacion:id_estacion,nombre',
                'usuario:id_usuario,nombres',
                'detalles' => fn ($query) => $query->with([
                    'modificadores', 'pedido:id_pedido,numero,tipo,id_mesa,nombre_contacto', 'pedido.mesa:id_mesa,codigo',
                    'producto:id_producto,id_categoria', 'producto.categoria:id_categoria,nombre',
                ])->orderBy('id_detalle_pedido'),
            ]);
        $activas = (clone $comandas)->whereIn('estado', Comanda::ACTIVAS)
            ->orderBy('enviada_en')->orderBy('id_comanda')->limit(200)->get();
        $entregadas = $comandas->where('estado', Comanda::ENTREGADA)
            ->where('entregada_en', '>=', now()->subHours(12))
            ->latest('entregada_en')->latest('id_comanda')->limit(30)->get();
        $comandas = $activas->concat($entregadas);

        $impresion = $this->impresion->estados($comandas->modelKeys());

        return $comandas->map(fn (Comanda $comanda): array => [
            'id_comanda' => $comanda->id_comanda,
            'numero' => $comanda->numero,
            'estado' => $comanda->estado,
            'estacion' => $comanda->estacion?->nombre ?? 'Cocina general',
            'enviada_en' => $comanda->enviada_en?->toIso8601String(),
            'iniciada_en' => $comanda->iniciada_en?->toIso8601String(),
            'lista_en' => $comanda->lista_en?->toIso8601String(),
            'entregada_en' => $comanda->entregada_en?->toIso8601String(),
            'mozo' => $comanda->usuario?->nombres,
            'pedido' => [
                'id_pedido' => $comanda->pedido->id_pedido,
                'numero' => $comanda->pedido->numero,
                'tipo' => $comanda->pedido->tipo,
                'mesa' => $comanda->pedido->mesa?->codigo,
                'nombre_contacto' => $comanda->pedido->nombre_contacto,
                'comensales' => $comanda->pedido->comensales,
                'observacion' => $comanda->pedido->observacion,
            ],
            'impresion' => $impresion->get($comanda->id_comanda),
            'items' => $comanda->detalles->map(fn (PedidoDetalle $detalle): array => [
                'id_detalle_pedido' => $detalle->id_detalle_pedido,
                'cantidad' => (float) $detalle->cantidad,
                'nombre' => $detalle->nombre_producto,
                'distintivo' => DistintivoCategoria::deProducto($detalle->producto),
                'opciones' => $detalle->modificadores->map->etiqueta((float) $detalle->cantidad)->values()->all(),
                'observacion' => $detalle->observacion,
                'anulado' => $detalle->estaAnulado(),
                'cuenta' => (int) $detalle->id_pedido === (int) $comanda->id_pedido ? null : [
                    'numero' => $detalle->pedido->numero,
                    'tipo' => $detalle->pedido->tipo,
                    'mesa' => $detalle->pedido->mesa?->codigo,
                    'nombre_contacto' => $detalle->pedido->nombre_contacto,
                ],
            ])->values()->all(),
        ])->values()->all();
    }

    /** @return array<int, array{id_estacion:int, nombre:string}> estaciones activas para el filtro del KDS */
    public function estaciones(int $sucursalId): array
    {
        return Estacion::query()->where('id_sucursal', $sucursalId)->where('activo', true)
            ->orderBy('orden')->orderBy('nombre')
            ->get(['id_estacion', 'nombre'])
            ->map(fn (Estacion $estacion): array => ['id_estacion' => $estacion->id_estacion, 'nombre' => $estacion->nombre])
            ->all();
    }

    public function cambiarEstado(int $comandaId, string $nuevo, int $sucursalId): Comanda
    {
        return DB::transaction(function () use ($comandaId, $nuevo, $sucursalId): Comanda {
            $comanda = Comanda::query()->where('id_sucursal', $sucursalId)->find($comandaId);
            if (! $comanda) {
                throw new DomainException('La comanda no existe en esta sucursal.');
            }
            // Pedido y transferencias bloquean líneas antes de cambiar su comanda.
            $comanda->detalles()->orderBy('id_detalle_pedido')->lockForUpdate()->get(['id_detalle_pedido']);
            $comanda = $this->comandaBloqueada($comandaId, $sucursalId);

            if ($comanda->estado === $nuevo) {
                return $comanda; // Doble toque en la pantalla: sin efecto.
            }
            if (! in_array($nuevo, self::TRANSICIONES[$comanda->estado] ?? [], true)) {
                throw new DomainException("La comanda #{$comanda->numero} no puede pasar de {$comanda->estado} a {$nuevo}.");
            }

            $cambios = ['estado' => $nuevo, self::MARCA_TIEMPO[$nuevo] => now()];
            if ($nuevo === Comanda::LISTA && ! $comanda->iniciada_en) {
                $cambios['iniciada_en'] = $comanda->enviada_en;
            }
            if ($nuevo === Comanda::EN_PREPARACION) {
                $cambios['lista_en'] = null;
            }
            $comanda->update($cambios);

            $comanda->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)->update(['estado' => self::ESTADO_ITEM[$nuevo]]);

            return $comanda->refresh();
        }, 3);
    }

    public function reimprimir(int $comandaId, string $solicitud, int $sucursalId): ?TrabajoImpresion
    {
        $comanda = Comanda::query()->where('id_sucursal', $sucursalId)->find($comandaId);
        if (! $comanda) {
            throw new DomainException('La comanda no existe en esta sucursal.');
        }

        return $this->impresion->reimprimir($comanda, $solicitud);
    }

    /**
     * Ítems ya enviados que se anularon (se llama dentro de la transacción de anulación).
     * Si una comanda se queda sin ítems vigentes, pasa a ANULADA. La impresión del aviso
     * se guarda en la misma transacción y se procesa de forma independiente.
     *
     * @param  Collection<int, PedidoDetalle>  $detalles
     */
    public function itemsAnulados(Collection $detalles): void
    {
        $porComanda = $detalles->whereNotNull('id_comanda')->groupBy('id_comanda');

        foreach ($porComanda as $comandaId => $anulados) {
            $comanda = Comanda::withoutGlobalScopes()->lockForUpdate()->find($comandaId);
            if (! $comanda || $comanda->estado === Comanda::ANULADA) {
                continue;
            }

            $vigentes = $comanda->detalles()->where('estado', '!=', PedidoDetalle::ANULADO)->exists();
            if (! $vigentes && $comanda->estado !== Comanda::ENTREGADA) {
                $comanda->update(['estado' => Comanda::ANULADA]);
            }

            if ($comanda->estado !== Comanda::ENTREGADA) {
                $this->impresion->anulacion($comanda, $anulados);
            }
        }
    }

    /** Tras mover ítems entre cuentas, cada comanda apunta a la cuenta donde quedaron sus ítems. */
    public function sincronizarPedidos(array $comandaIds): void
    {
        foreach (array_unique(array_filter($comandaIds)) as $comandaId) {
            $pedido = PedidoDetalle::query()->where('id_comanda', $comandaId)->where('estado', '!=', PedidoDetalle::ANULADO)
                ->orderBy('id_detalle_pedido')->value('id_pedido');
            if ($pedido) {
                Comanda::withoutGlobalScopes()->whereKey($comandaId)->update(['id_pedido' => $pedido]);
            }
        }
    }

    private function comandaBloqueada(int $comandaId, int $sucursalId): Comanda
    {
        $comanda = Comanda::query()->where('id_sucursal', $sucursalId)->lockForUpdate()->find($comandaId);
        if (! $comanda) {
            throw new DomainException('La comanda no existe en esta sucursal.');
        }

        return $comanda;
    }
}
