<?php

namespace App\Services\Restaurante;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carta digital pública, con el mismo formato que acepta ImportarCartaService:
 * { sedes: { slug: {...} }, categorias: [...], carta: [ { categoria, iconoNombre, oferta, platos: [...] } ] }
 *
 * Solo incluye sucursales activas con slug, productos activos que no son insumos y
 * categorías visibles. Un plato aparece en una sede solo si se vende allí; los agotados
 * del momento se listan en "agotadoEn" para que la web pueda atenuarlos.
 */
class CartaPublicaService
{
    public function __construct(private readonly PrecioSucursalService $precios) {}

    public function carta(?string $slug = null): ?array
    {
        $sucursales = Sucursal::query()
            ->where('estado', true)
            ->whereNotNull('slug')
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->orderBy('nombre')
            ->get(['id_sucursal', 'slug', 'nombre', 'direccion', 'telefono', 'horario_atencion']);

        if ($slug && $sucursales->isEmpty()) {
            return null;
        }

        $productos = Producto::query()
            ->where('estado', true)
            ->where('tipo_producto', '!=', Producto::INSUMO)
            ->with(['presentaciones' => fn ($query) => $query->where('estado', true)->orderBy('factor_conversion')])
            ->orderBy('nombre')
            ->get(['id_producto', 'id_categoria', 'nombre', 'codigo_carta', 'codigo_externo'])
            ->sortBy(fn (Producto $producto) => PresentarRestauranteService::ordenCarta($producto))
            ->values();

        // La carta usa la presentación base (menor factor) de cada producto.
        $precioBase = $productos->mapWithKeys(fn (Producto $producto) => [$producto->id_producto => $producto->presentaciones->first()])->filter();
        $ajustes = $sucursales->mapWithKeys(fn (Sucursal $sucursal) => [
            $sucursal->id_sucursal => $this->precios->ajustes($precioBase->map->id_precio->values()->all(), $sucursal->id_sucursal),
        ]);
        $agotados = DB::table('restaurante.productos_agotados')
            ->whereIn('id_sucursal', $sucursales->modelKeys())
            ->get(['id_sucursal', 'id_producto'])
            ->groupBy('id_producto');

        $categorias = $this->categoriasVisibles($productos->pluck('id_categoria')->unique()->all());

        $carta = $categorias->map(function (array $categoria) use ($productos, $precioBase, $sucursales, $ajustes, $agotados): ?array {
            $platos = $productos->where('id_categoria', $categoria['id_categoria'])->map(function (Producto $producto) use ($precioBase, $sucursales, $ajustes, $agotados): ?array {
                $base = $precioBase->get($producto->id_producto);
                if (! $base) {
                    return null;
                }

                $precios = [];
                foreach ($sucursales as $sucursal) {
                    $ajuste = $ajustes[$sucursal->id_sucursal]->get($base->id_precio);
                    if ($ajuste && ! $ajuste->disponible) {
                        continue;
                    }
                    $precios[$sucursal->slug] = round((float) ($ajuste->precio_venta ?? $base->precio_venta), 2);
                }
                if ($precios === []) {
                    return null;
                }

                $agotadoEn = ($agotados->get($producto->id_producto) ?? collect())
                    ->map(fn ($fila) => $sucursales->firstWhere('id_sucursal', $fila->id_sucursal)?->slug)
                    ->filter()->values()->all();

                return array_filter([
                    'id' => $producto->codigo_externo ?? 'p'.$producto->id_producto,
                    'nro' => $producto->codigo_carta,
                    'nombre' => $producto->nombre,
                    'precios' => $precios,
                    'agotadoEn' => $agotadoEn ?: null,
                ], fn ($valor) => $valor !== null);
            })->filter()->values();

            if ($platos->isEmpty()) {
                return null;
            }

            return array_filter([
                'categoria' => $categoria['nombre'],
                'iconoNombre' => $categoria['icono'],
                'oferta' => $categoria['nota'],
                'platos' => $platos->all(),
            ], fn ($valor) => $valor !== null);
        })->filter()->values();

        return [
            'sedes' => $sucursales->mapWithKeys(fn (Sucursal $sucursal) => [$sucursal->slug => array_filter([
                'id' => $sucursal->slug,
                'nombre' => $sucursal->nombre,
                'direccion' => $sucursal->direccion,
                'telefono' => $sucursal->telefono,
                'horario' => $sucursal->horario_atencion,
            ], fn ($valor) => $valor !== null)])->all(),
            'categorias' => ['Todas', ...$carta->pluck('categoria')->all()],
            'carta' => $carta->all(),
            'actualizado_en' => now()->toIso8601String(),
        ];
    }

    /** @return Collection<int, array{id_categoria:int, nombre:string, icono:?string, nota:?string}> */
    private function categoriasVisibles(array $ids): Collection
    {
        $presentacion = DB::table('restaurante.categorias_carta')->whereIn('id_categoria', $ids)->get()->keyBy(fn ($fila) => (int) $fila->id_categoria);

        return Categoria::query()
            ->whereIn('id_categoria', $ids)
            ->where('estado', true)
            ->get(['id_categoria', 'nombre'])
            ->map(fn (Categoria $categoria): array => [
                'id_categoria' => (int) $categoria->id_categoria,
                'nombre' => $categoria->nombre,
                'icono' => $presentacion->get($categoria->id_categoria)?->icono,
                'nota' => $presentacion->get($categoria->id_categoria)?->nota,
                'orden' => (int) ($presentacion->get($categoria->id_categoria)?->orden ?? 999),
                'visible' => (bool) ($presentacion->get($categoria->id_categoria)?->visible ?? true),
            ])
            ->filter(fn (array $categoria) => $categoria['visible'])
            ->sortBy([['orden', 'asc'], ['nombre', 'asc']])
            ->values();
    }
}
