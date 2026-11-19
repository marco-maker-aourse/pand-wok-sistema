<?php

namespace App\Services\Restaurante;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\Area;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\GrupoModificador;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\Restaurante\PedidoEvento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de solo lectura para las pantallas del restaurante. Todo importe sale de
 * la base (subtotales congelados); React solo muestra. Siempre filtra por sucursal.
 *
 * Las relaciones muchos-a-muchos se resuelven con consultas simples sobre la tabla
 * pivote para no depender de nombres de tres partes (esquema.tabla.*).
 */
class PresentarRestauranteService
{
    public function __construct(
        private readonly GestionarCartaService $carta,
        private readonly PrecioSucursalService $precios,
        private readonly ImprimirComandaService $impresion,
    ) {}

    /** Plano del salón: áreas activas, sus mesas y la cuenta abierta de cada una. */
    public function salon(int $sucursalId): array
    {
        $cuentas = $this->cuentasAbiertas($sucursalId);
        $porMesa = $cuentas->whereNotNull('id_mesa')->keyBy('id_mesa');

        $areas = Area::query()
            ->where('id_sucursal', $sucursalId)
            ->where('activo', true)
            ->with(['mesas' => fn ($query) => $query->where('activo', true)->orderBy('orden')->orderBy('codigo')])
            ->orderBy('orden')->orderBy('nombre')
            ->get()
            ->map(fn (Area $area): array => [
                'id_area' => $area->id_area,
                'nombre' => $area->nombre,
                'mesas' => $area->mesas->map(fn (Mesa $mesa): array => [
                    'id_mesa' => $mesa->id_mesa,
                    'codigo' => $mesa->codigo,
                    'capacidad' => $mesa->capacidad,
                    'estado' => $mesa->estado,
                    'cuenta' => $porMesa->get($mesa->id_mesa),
                ])->values(),
            ])->values();

        $mesas = $areas->flatMap(fn (array $area) => $area['mesas']);

        return [
            'areas' => $areas,
            'cuentasSinMesa' => $cuentas->whereNull('id_mesa')->values(),
            'resumen' => [
                'mesas' => $mesas->count(),
                'ocupadas' => $mesas->where('estado', Mesa::OCUPADA)->count(),
                'libres' => $mesas->where('estado', Mesa::DISPONIBLE)->count(),
                'reservadas' => $mesas->where('estado', Mesa::RESERVADA)->count(),
                'cuentas' => $cuentas->count(),
                'consumo' => round((float) $cuentas->sum('total'), 2),
            ],
        ];
    }

    /** Áreas y mesas (también inactivas) para la pantalla de configuración. */
    public function configuracionSalon(int $sucursalId): array
    {
        return Area::query()
            ->where('id_sucursal', $sucursalId)
            ->with(['mesas' => fn ($query) => $query->orderBy('orden')->orderBy('codigo')])
            ->orderBy('orden')->orderBy('nombre')
            ->get()
            ->map(fn (Area $area): array => [
                'id_area' => $area->id_area,
                'nombre' => $area->nombre,
                'orden' => $area->orden,
                'activo' => $area->activo,
                'mesas' => $area->mesas->map->only(['id_mesa', 'id_area', 'codigo', 'capacidad', 'estado', 'orden', 'activo'])->values(),
            ])->values()->all();
    }

    /** Cuenta completa con ítems, modificadores, total vigente e historial reciente. */
    public function pedido(Pedido $pedido): array
    {
        $pedido->loadMissing(['mesa.area:id_area,nombre', 'usuario:id_usuario,nombres']);
        $detalles = $pedido->detalles()->with('modificadores')->orderBy('id_detalle_pedido')->get();

        $comandas = Comanda::query()->where('id_sucursal', $pedido->id_sucursal)
            ->whereKey($detalles->pluck('id_comanda')->filter()->unique()->values()->all())
            ->with('estacion:id_estacion,nombre')->orderByDesc('id_comanda')->get();
        $impresion = $this->impresion->estados($comandas->modelKeys());

        $usuarios = DB::table('seguridad.usuarios')
            ->whereIn('id_usuario', PedidoEvento::query()->where('id_pedido', $pedido->id_pedido)->select('id_usuario'))
            ->pluck('nombres', 'id_usuario');

        return [
            'id_pedido' => $pedido->id_pedido,
            'numero' => $pedido->numero,
            'tipo' => $pedido->tipo,
            'estado' => $pedido->estado,
            'comensales' => $pedido->comensales,
            'nombre_contacto' => $pedido->nombre_contacto,
            'telefono_contacto' => $pedido->telefono_contacto,
            'direccion_entrega' => $pedido->direccion_entrega,
            'observacion' => $pedido->observacion,
            'abierto_en' => $pedido->abierto_en?->toIso8601String(),
            'cerrado_en' => $pedido->cerrado_en?->toIso8601String(),
            'motivo_anulacion' => $pedido->motivo_anulacion,
            'id_pedido_destino' => $pedido->id_pedido_destino,
            'mozo' => $pedido->usuario?->nombres,
            'mesa' => $pedido->mesa ? [
                'id_mesa' => $pedido->mesa->id_mesa,
                'codigo' => $pedido->mesa->codigo,
                'area' => $pedido->mesa->area?->nombre,
            ] : null,
            'total' => round((float) $detalles->where('estado', '!=', PedidoDetalle::ANULADO)->sum('subtotal'), 2),
            'saldo' => round((float) $detalles->where('estado', '!=', PedidoDetalle::ANULADO)->sum(
                fn (PedidoDetalle $detalle): float => (float) $detalle->subtotal - ArmarItemPedidoService::subtotal((float) $detalle->cantidad_cobrada, (float) $detalle->precio_unitario),
            ), 2),
            'detalles' => $detalles->map(fn (PedidoDetalle $detalle): array => [
                'id_detalle_pedido' => $detalle->id_detalle_pedido,
                'nombre_producto' => $detalle->nombre_producto,
                'cantidad' => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
                'observacion' => $detalle->observacion,
                'estado' => $detalle->estado,
                'cantidad_cobrada' => (float) $detalle->cantidad_cobrada,
                'motivo_anulacion' => $detalle->motivo_anulacion,
                'modificadores' => $detalle->modificadores->map(fn ($m): array => [
                    'nombre_grupo' => $m->nombre_grupo,
                    'nombre_opcion' => $m->nombre_opcion,
                    'precio_adicional' => (float) $m->precio_adicional,
                ])->values(),
            ])->values(),
            'comandas' => $comandas->map(fn (Comanda $comanda): array => [
                'id_comanda' => $comanda->id_comanda,
                'numero' => $comanda->numero,
                'estado' => $comanda->estado,
                'estacion' => $comanda->estacion?->nombre ?? 'Cocina general',
                'enviada_en' => $comanda->enviada_en?->toIso8601String(),
                'impresion' => $impresion->get($comanda->id_comanda),
            ])->values(),
            'eventos' => PedidoEvento::query()
                ->where('id_pedido', $pedido->id_pedido)
                ->latest('id_evento')
                ->limit(40)
                ->get()
                ->map(fn (PedidoEvento $evento): array => [
                    'id_evento' => $evento->id_evento,
                    'tipo' => $evento->tipo,
                    'datos' => $evento->datos,
                    'usuario' => $usuarios[$evento->id_usuario] ?? null,
                    'creado_en' => $evento->creado_en?->toIso8601String(),
                ])->values(),
        ];
    }

    /** Destinos posibles para transferir una cuenta: mesas libres y otras cuentas abiertas. */
    public function destinosTransferencia(Pedido $pedido): array
    {
        return [
            'mesasLibres' => Mesa::query()
                ->where('id_sucursal', $pedido->id_sucursal)
                ->where('activo', true)
                ->whereIn('estado', [Mesa::DISPONIBLE, Mesa::RESERVADA])
                ->with('area:id_area,nombre')
                ->orderBy('codigo')
                ->get(['id_mesa', 'id_area', 'codigo', 'capacidad', 'estado'])
                ->map(fn (Mesa $mesa): array => [
                    'id_mesa' => $mesa->id_mesa,
                    'codigo' => $mesa->codigo,
                    'capacidad' => $mesa->capacidad,
                    'estado' => $mesa->estado,
                    'area' => $mesa->area?->nombre,
                ])->values(),
            'otrasCuentas' => $this->cuentasAbiertas($pedido->id_sucursal)
                ->where('id_pedido', '!=', $pedido->id_pedido)
                ->values(),
        ];
    }

    /**
     * Carta vendible de la sucursal para la toma de pedidos: categorías, productos con
     * sus presentaciones, grupos de modificadores y si están agotados.
     */
    public function cartaPedido(int $sucursalId): array
    {
        $productos = Producto::query()
            ->where('estado', true)
            ->where('tipo_producto', '!=', Producto::INSUMO)
            ->whereHas('presentaciones', fn ($query) => $query->where('estado', true))
            ->with(['presentaciones' => fn ($query) => $query->where('estado', true)->with('presentacion:id_presentacion,nombre')->orderBy('factor_conversion')])
            ->orderBy('nombre')
            ->limit(1500)
            ->get(['id_producto', 'id_categoria', 'nombre', 'tipo_producto', 'codigo_carta'])
            ->sortBy(fn (Producto $producto) => self::ordenCarta($producto))
            ->values();

        $gruposPorProducto = $this->gruposPorProducto($productos->modelKeys());
        $agotados = array_flip($this->carta->agotados($sucursalId));
        $ajustes = $this->precios->ajustes($productos->flatMap->presentaciones->pluck('id_precio')->all(), $sucursalId);

        // Precio de la sucursal; se ocultan las presentaciones que no se venden aquí.
        $productos = $productos->map(function (Producto $producto) use ($agotados, $ajustes, $gruposPorProducto): ?array {
            $presentaciones = $producto->presentaciones
                ->reject(fn (ProductoPresentacion $precio) => ($ajuste = $ajustes->get($precio->id_precio)) && ! $ajuste->disponible)
                ->map(fn (ProductoPresentacion $precio): array => [
                    'id_precio' => $precio->id_precio,
                    'nombre' => $precio->presentacion?->nombre ?? 'Unidad',
                    'precio_venta' => round((float) ($ajustes->get($precio->id_precio)?->precio_venta ?? $precio->precio_venta), 2),
                ])->values();

            return $presentaciones->isEmpty() ? null : [
                'id_producto' => $producto->id_producto,
                'id_categoria' => $producto->id_categoria,
                'nombre' => $producto->nombre,
                'codigo_carta' => $producto->codigo_carta,
                'tipo_producto' => $producto->tipo_producto,
                'agotado' => isset($agotados[$producto->id_producto]),
                'grupos' => $gruposPorProducto->get($producto->id_producto, []),
                'presentaciones' => $presentaciones,
            ];
        })->filter()->values();

        $idsGrupos = $productos->pluck('grupos')->flatten()->unique()->values()->all();
        $orden = DB::table('restaurante.categorias_carta')->pluck('orden', 'id_categoria');

        return [
            'categorias' => Categoria::query()
                ->whereIn('id_categoria', $productos->pluck('id_categoria')->unique())
                ->get(['id_categoria', 'nombre'])
                ->sortBy(fn (Categoria $categoria) => [(int) ($orden[$categoria->id_categoria] ?? 999), $categoria->nombre])
                ->values(),
            'productos' => $productos,
            'grupos' => $this->gruposConOpciones($idsGrupos, true),
        ];
    }

    /** Catálogo para administrar la carta, paginado y con filtros. */
    public function cartaAdministracion(int $sucursalId, ?string $buscar, ?string $tipo): array
    {
        /** @var LengthAwarePaginator $pagina */
        $pagina = Producto::query()
            ->where('estado', true)
            ->when($tipo, fn (Builder $query, string $valor) => $query->where('tipo_producto', $valor))
            ->when($buscar, fn (Builder $query, string $texto) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']))
            ->with([
                'categoria:id_categoria,nombre',
                'presentaciones' => fn ($query) => $query->where('estado', true)->orderBy('factor_conversion'),
            ])
            ->orderBy('nombre')
            ->paginate(25, ['id_producto', 'id_categoria', 'nombre', 'tipo_producto', 'codigo_carta'])
            ->withQueryString();

        $items = collect($pagina->items());
        $gruposPorProducto = $this->gruposPorProducto($items->pluck('id_producto')->all());
        $agotados = array_flip($this->carta->agotados($sucursalId));
        $ajustes = $this->precios->ajustes($items->flatMap->presentaciones->pluck('id_precio')->all(), $sucursalId);

        // Se administra el precio de la presentación base (la que muestra la carta).
        $pagina->through(function (Producto $producto) use ($agotados, $ajustes, $gruposPorProducto): array {
            $base = $producto->presentaciones->first();
            $ajuste = $base ? $ajustes->get($base->id_precio) : null;

            return [
                'id_producto' => $producto->id_producto,
                'nombre' => $producto->nombre,
                'codigo_carta' => $producto->codigo_carta,
                'categoria' => $producto->categoria?->nombre,
                'tipo_producto' => $producto->tipo_producto,
                'id_precio' => $base?->id_precio,
                'precio_general' => $base ? (float) $base->precio_venta : null,
                'precio_sucursal' => $ajuste?->precio_venta !== null ? (float) $ajuste->precio_venta : null,
                'vende_en_sucursal' => ! $ajuste || (bool) $ajuste->disponible,
                'agotado' => isset($agotados[$producto->id_producto]),
                'grupos' => $gruposPorProducto->get($producto->id_producto, []),
            ];
        });

        return [
            'productos' => $pagina,
            'grupos' => $this->gruposConOpciones(null, false),
        ];
    }

    /** Orden de carta: por número de plato (numérico: 2 antes que 10), luego sin número, por nombre. */
    public static function ordenCarta(Producto $producto): array
    {
        $codigo = $producto->codigo_carta;
        $numerico = $codigo !== null && is_numeric($codigo);

        return [$codigo === null ? 1 : 0, $numerico ? (int) $codigo : PHP_INT_MAX, (string) $codigo, $producto->nombre];
    }

    /** @return Collection<int, Collection> cuentas abiertas de la sucursal con total vigente */
    private function cuentasAbiertas(int $sucursalId): Collection
    {
        $totales = PedidoDetalle::query()
            ->join('restaurante.pedidos as p', 'p.id_pedido', '=', 'restaurante.pedido_detalles.id_pedido')
            ->where('p.id_sucursal', $sucursalId)
            ->where('p.estado', Pedido::ABIERTO)
            ->where('restaurante.pedido_detalles.estado', '!=', PedidoDetalle::ANULADO)
            ->groupBy('restaurante.pedido_detalles.id_pedido')
            ->selectRaw('restaurante.pedido_detalles.id_pedido as id_pedido, SUM(subtotal) as total, COUNT(*) as items')
            ->get()
            ->keyBy('id_pedido');

        $mozos = DB::table('seguridad.usuarios')
            ->whereIn('id_usuario', Pedido::query()->where('id_sucursal', $sucursalId)->where('estado', Pedido::ABIERTO)->select('id_usuario'))
            ->pluck('nombres', 'id_usuario');

        return Pedido::query()
            ->where('id_sucursal', $sucursalId)
            ->where('estado', Pedido::ABIERTO)
            ->with('mesa:id_mesa,codigo')
            ->orderBy('abierto_en')
            ->get(['id_pedido', 'numero', 'tipo', 'id_mesa', 'id_usuario', 'comensales', 'nombre_contacto', 'abierto_en'])
            ->map(fn (Pedido $pedido): array => [
                'id_pedido' => $pedido->id_pedido,
                'numero' => $pedido->numero,
                'tipo' => $pedido->tipo,
                'id_mesa' => $pedido->id_mesa,
                'mesa' => $pedido->mesa?->codigo,
                'comensales' => $pedido->comensales,
                'nombre_contacto' => $pedido->nombre_contacto,
                'mozo' => $mozos[$pedido->id_usuario] ?? null,
                'abierto_en' => $pedido->abierto_en?->toIso8601String(),
                'total' => round((float) ($totales[$pedido->id_pedido]->total ?? 0), 2),
                'items' => (int) ($totales[$pedido->id_pedido]->items ?? 0),
            ]);
    }

    /** @return Collection<int, array<int>> ids de grupo por producto, en el orden configurado */
    private function gruposPorProducto(array $productoIds): Collection
    {
        if ($productoIds === []) {
            return collect();
        }

        return DB::table('restaurante.producto_grupo_modificador')
            ->whereIn('id_producto', $productoIds)
            ->orderBy('orden')
            ->get(['id_producto', 'id_grupo'])
            ->groupBy('id_producto')
            ->map(fn (Collection $filas) => $filas->pluck('id_grupo')->map(fn ($id) => (int) $id)->values()->all());
    }

    /** @param  array<int>|null  $ids  null = todos los grupos */
    private function gruposConOpciones(?array $ids, bool $soloActivos): array
    {
        if ($ids === []) {
            return [];
        }

        return GrupoModificador::query()
            ->when($ids !== null, fn ($query) => $query->whereKey($ids))
            ->when($soloActivos, fn ($query) => $query->where('activo', true))
            ->with(['opciones' => fn ($query) => $query
                ->when($soloActivos, fn ($q) => $q->where('activo', true))
                ->orderBy('orden')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (GrupoModificador $grupo): array => [
                'id_grupo' => $grupo->id_grupo,
                'nombre' => $grupo->nombre,
                'seleccion_minima' => $grupo->seleccion_minima,
                'seleccion_maxima' => $grupo->seleccion_maxima,
                'activo' => $grupo->activo,
                'opciones' => $grupo->opciones->map(fn ($opcion): array => [
                    'id_opcion' => $opcion->id_opcion,
                    'nombre' => $opcion->nombre,
                    'precio_adicional' => (float) $opcion->precio_adicional,
                    'orden' => $opcion->orden,
                    'activo' => $opcion->activo,
                ])->values(),
            ])->values()->all();
    }
}
