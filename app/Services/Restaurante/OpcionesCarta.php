<?php

namespace App\Services\Restaurante;

use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\GrupoModificador;
use App\Models\Restaurante\OpcionModificador;
use App\Models\UnidadMedida;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operaciones repetibles para armar grupos de opciones de la carta (sopas del menú,
 * adicionales de combinación). Nunca duplica y nunca pisa lo que el administrador ajustó
 * después: precios generales, precios por sucursal ni el orden de las opciones.
 */
class OpcionesCarta
{
    /** Busca el grupo por nombre (sin distinguir mayúsculas) o lo crea con sus reglas. */
    public function grupo(string $nombre, int $minima, int $maxima): GrupoModificador
    {
        return GrupoModificador::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first()
            ?? GrupoModificador::query()->create([
                'nombre' => $nombre, 'seleccion_minima' => $minima, 'seleccion_maxima' => $maxima, 'activo' => true,
            ]);
    }

    /** Opción vinculada a una variante de la carta (descuenta su receta o stock). */
    public function opcionProducto(GrupoModificador $grupo, ProductoPresentacion $precio, float $adicional, int $orden): OpcionModificador
    {
        return OpcionModificador::query()->firstOrCreate(
            ['id_grupo' => $grupo->id_grupo, 'id_precio' => $precio->id_precio],
            ['nombre' => null, 'precio_adicional' => $adicional, 'orden' => $orden, 'activo' => true],
        );
    }

    /** Opción de solo texto (p. ej. "3 wantanes fritos"). */
    public function opcionTexto(GrupoModificador $grupo, string $nombre, float $adicional, int $orden): OpcionModificador
    {
        return OpcionModificador::query()->firstOrCreate(
            ['id_grupo' => $grupo->id_grupo, 'nombre' => $nombre],
            ['id_precio' => null, 'precio_adicional' => $adicional, 'orden' => $orden, 'activo' => true],
        );
    }

    /** Agrega el grupo a los productos al final de sus grupos actuales. Devuelve cuántos son nuevos. */
    public function asignar(GrupoModificador $grupo, array $productos): int
    {
        $nuevos = 0;
        foreach (array_unique(array_map('intval', $productos)) as $productoId) {
            $siguiente = (int) DB::table('restaurante.producto_grupo_modificador')->where('id_producto', $productoId)->max('orden') + 1;
            $nuevos += DB::table('restaurante.producto_grupo_modificador')->insertOrIgnore([
                'id_producto' => $productoId, 'id_grupo' => $grupo->id_grupo, 'orden' => $siguiente,
            ]);
        }

        return $nuevos;
    }

    /** Platos preparados y activos de una categoría (sin distinguir mayúsculas). */
    public function platosDeCategoria(string $categoria): ?Collection
    {
        $registro = Categoria::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($categoria)])->first();

        return $registro ? Producto::query()->where('id_categoria', $registro->id_categoria)
            ->where('tipo_producto', Producto::PREPARADO)->where('estado', true)->pluck('id_producto') : null;
    }

    /**
     * Variante de una sopa: la busca por prefijo entre los productos existentes ("Sopa Wantan",
     * "Sopa de Patitas (Sáb. Dom.)"). Si no existe la crea en "Sopas" con precio 0 y sin
     * venderse sola en ninguna sucursal: no se inventan precios.
     *
     * @return array{0: ProductoPresentacion, 1: bool} variante y si se creó
     */
    public function sopa(string $nombre, string $prefijo): array
    {
        $producto = Producto::query()->where('tipo_producto', '!=', Producto::INSUMO)->get(['id_producto', 'nombre'])
            ->first(fn (Producto $p) => str_starts_with(self::normalizar($p->nombre), $prefijo));
        $precio = $producto
            ? ProductoPresentacion::query()->where('id_producto', $producto->id_producto)->where('estado', true)->orderBy('factor_conversion')->first()
            : null;

        return $precio ? [$precio, false] : [$this->crearSopa($nombre), true];
    }

    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->value();
    }

    private function crearSopa(string $nombre): ProductoPresentacion
    {
        $categoria = Categoria::query()->whereRaw('LOWER(nombre) = ?', ['sopas'])->first()
            ?? Categoria::query()->create(['nombre' => 'Sopas', 'estado' => true]);
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo_sunat' => UnidadMedida::UNIDAD], ['nombre' => 'Unidad', 'estado' => true]);
        $presentacion = Presentacion::query()->whereRaw('LOWER(nombre) = ?', ['unidad'])->first()
            ?? Presentacion::query()->create(['nombre' => 'Unidad', 'estado' => true]);

        $producto = Producto::query()->create([
            'nombre' => $nombre, 'id_categoria' => $categoria->id_categoria, 'id_unidad_base' => $unidad->id_unidad,
            'tipo_producto' => Producto::PREPARADO, 'afecta_igv' => true, 'es_servicio' => false, 'estado' => true,
        ]);
        $precio = ProductoPresentacion::query()->create([
            'id_producto' => $producto->id_producto, 'id_presentacion' => $presentacion->id_presentacion,
            'factor_conversion' => 1, 'precio_venta' => 0, 'estado' => true,
        ]);

        // Sin precio real todavía: no se vende sola en ninguna sucursal, solo como opción.
        foreach (DB::table('configuracion.sucursales')->pluck('id_sucursal') as $sucursal) {
            DB::table('restaurante.precios_sucursal')->insertOrIgnore([
                'id_sucursal' => $sucursal, 'id_precio' => $precio->id_precio, 'precio_venta' => null, 'disponible' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $precio;
    }
}
