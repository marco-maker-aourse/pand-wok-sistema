<?php

namespace App\Services\Restaurante;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecetasService
{
    /** Tipos de producto con stock propio que pueden darse de baja como merma. */
    private const TIPOS_CON_MERMA = [Producto::INSUMO, Producto::DIRECTO];

    public function __construct(private readonly GestionarExistenciaService $inventario) {}

    public function guardar(int $plato, int $sucursal, array $ingredientes): void
    {
        DB::transaction(function () use ($plato, $sucursal, $ingredientes): void {
            DB::table('configuracion.sucursales')->where('id_sucursal', $sucursal)->lockForUpdate()->first();
            $this->presentaciones(Producto::PREPARADO)->findOrFail($plato);
            $ids = array_column($ingredientes, 'id_precio_insumo');
            $precios = $this->presentaciones(Producto::INSUMO)->whereKey($ids)->get()->keyBy('id_precio');
            if ($precios->count() !== count($ids) || $precios->pluck('id_producto')->unique()->count() !== count($ids)) {
                throw new DomainException('Selecciona una sola presentación activa por ingrediente.');
            }
            $nuevos = [];
            foreach ($ingredientes as $ingrediente) {
                $insumo = $precios[$ingrediente['id_precio_insumo']];
                if ($this->inventario->toBaseQuantity($insumo, (float) $ingrediente['cantidad']) <= 0) {
                    throw new DomainException('La cantidad de un ingrediente es demasiado pequeña para su conversión.');
                }
                $nuevos[] = [...$ingrediente, 'id_sucursal' => $sucursal, 'id_precio_plato' => $plato];
            }
            DB::table('restaurante.receta_insumos')->where('id_sucursal', $sucursal)->where('id_precio_plato', $plato)->delete();
            DB::table('restaurante.receta_insumos')->insert($nuevos);
        }, 3);
    }

    public function registrarMerma(int $sucursal, int $usuario, array $datos): int
    {
        return DB::transaction(function () use ($sucursal, $usuario, $datos): int {
            DB::table('configuracion.sucursales')->where('id_sucursal', $sucursal)->lockForUpdate()->first();
            $huella = hash('sha256', json_encode([$usuario, $datos], JSON_THROW_ON_ERROR));
            $previa = DB::table('restaurante.mermas')->where('id_sucursal', $sucursal)->where('clave', $datos['clave'])->first();
            if ($previa) {
                if ($previa->huella !== $huella) {
                    throw new DomainException('La clave de la merma ya se usó con otros datos.');
                }

                return $previa->id_merma;
            }

            // Se da de baja todo lo que tiene stock: insumos y productos de venta directa (una gaseosa rota).
            $insumo = $this->presentaciones(...self::TIPOS_CON_MERMA)->findOrFail($datos['id_precio']);
            $base = $this->inventario->toBaseQuantity($insumo, (float) $datos['cantidad']);
            if ($base <= 0) {
                throw new DomainException('La cantidad es demasiado pequeña para su conversión.');
            }
            $id = DB::table('restaurante.mermas')->insertGetId([
                'id_sucursal' => $sucursal, 'id_usuario' => $usuario, 'id_precio' => $insumo->id_precio,
                'cantidad_base' => $base, 'motivo' => $datos['motivo'], 'clave' => $datos['clave'],
                'huella' => $huella, 'registrada_en' => now(),
            ], 'id_merma');
            $costo = $this->inventario->disminuirBase($insumo->productoInventario(), $sucursal, $base,
                new OrigenMovimiento(OrigenMovimiento::MERMA, $id, "MERMA-{$id}", $usuario));
            DB::table('restaurante.mermas')->where('id_merma', $id)->update(['costo_unitario' => $costo]);

            return $id;
        }, 3);
    }

    public function catalogo(int $sucursal, bool $verRecetas): array
    {
        // Las recetas solo usan insumos; las mermas aceptan también productos de venta directa.
        $tipos = $verRecetas ? [Producto::INSUMO] : self::TIPOS_CON_MERMA;
        $insumos = $this->presentaciones(...$tipos)->with(['producto.unidadBase', 'presentacion', 'productoStock'])->get();
        $ultimos = Kardex::query()->where('id_sucursal', $sucursal)
            ->whereIn('id_producto', $insumos->map(fn (ProductoPresentacion $p) => $p->productoInventario()->id_producto)->unique())
            ->groupBy('id_producto')->selectRaw('MAX(id_kardex)');
        $saldos = Kardex::query()->whereIn('id_kardex', $ultimos)->get()->keyBy('id_producto');

        return [
            'insumos' => $this->nombres($insumos)->map(function (array $item) use ($insumos, $saldos, $verRecetas): array {
                $inventario = $insumos->firstWhere('id_precio', $item['id_precio'])->productoInventario()->id_producto;

                return [
                    ...$item,
                    'stock' => (float) ($saldos[$inventario]->stock_actual ?? 0),
                    'costo' => $verRecetas && isset($saldos[$inventario]) ? (float) $saldos[$inventario]->precio_costo : null,
                ];
            }),
            'platos' => $verRecetas ? $this->nombres($this->presentaciones(Producto::PREPARADO)->with(['producto.unidadBase', 'presentacion'])->get()) : [],
            'recetas' => $verRecetas ? DB::table('restaurante.receta_insumos')->where('id_sucursal', $sucursal)
                ->orderBy('id_precio_insumo')->get()->groupBy('id_precio_plato') : (object) [],
            'mermas' => $verRecetas ? [] : DB::table('restaurante.mermas as m')->where('m.id_sucursal', $sucursal)
                ->join('catalogos.producto_presentaciones as p', 'p.id_precio', '=', 'm.id_precio')
                ->join('catalogos.productos as prod', 'prod.id_producto', '=', 'p.id_producto')
                ->latest('m.id_merma')->limit(20)->get(['m.id_merma', 'm.cantidad_base', 'm.motivo', 'm.registrada_en', 'prod.nombre']),
        ];
    }

    private function presentaciones(string ...$tipos): Builder
    {
        return ProductoPresentacion::query()->where('estado', true)
            ->whereIn('id_producto', Producto::query()->whereIn('tipo_producto', $tipos)->where('estado', true)
                ->where('es_servicio', false)->select('id_producto'))
            ->with('producto')->orderBy('id_precio');
    }

    private function nombres(Collection $precios): Collection
    {
        return $precios->map(fn (ProductoPresentacion $precio): array => [
            'id_precio' => $precio->id_precio,
            'id_producto' => $precio->id_producto,
            'nombre' => $precio->producto->nombre,
            'presentacion' => $precio->presentacion?->nombre ?? 'Unidad base',
            'unidad' => $precio->producto->unidadBase?->nombre,
            'factor' => (float) $precio->factor_conversion,
        ])->sortBy('nombre')->values();
    }
}
