<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\OpcionModificador;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de los grupos (sopa del menú, acompañamientos, salsas…) tal como rigen en una sucursal.
 *
 * Regla única para el POS, la toma de pedidos y la administración:
 * - Nombre: el propio de la opción o, si no tiene, el del producto vinculado.
 * - Recargo: el de restaurante.opciones_sucursal si existe; si no, el general de la opción.
 *   Recargo 0 = incluido en el precio del plato.
 * - Estado: NO_DISPONIBLE si la sucursal la desactivó, la opción o su producto están
 *   inactivos; AGOTADA si su producto está agotado en la sucursal; si no, DISPONIBLE.
 * - Obligatoriedad: mínimo y máximo del vínculo plato-grupo; si no hay, los del grupo.
 */
class OpcionesSucursalService
{
    public const DISPONIBLE = 'DISPONIBLE';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';
    public const AGOTADA = 'AGOTADA';

    /**
     * Grupos asignados a cada producto, en orden, con su regla de selección efectiva.
     *
     * @param  array<int>  $productoIds
     * @return Collection<int, Collection<int, object{id_grupo:int, seleccion_minima:int, seleccion_maxima:int}>>
     */
    public function reglas(array $productoIds): Collection
    {
        if ($productoIds === []) {
            return collect();
        }

        return DB::table('restaurante.producto_grupo_modificador as pg')
            ->join('restaurante.grupos_modificadores as g', 'g.id_grupo', '=', 'pg.id_grupo')
            ->whereIn('pg.id_producto', $productoIds)
            ->where('g.activo', true)
            ->orderBy('pg.orden')
            ->get(['pg.id_producto', 'pg.id_grupo', 'g.nombre', 'pg.seleccion_minima as min_plato', 'pg.seleccion_maxima as max_plato',
                'g.seleccion_minima', 'g.seleccion_maxima'])
            ->map(fn (object $fila): object => (object) [
                'id_producto' => (int) $fila->id_producto,
                'id_grupo' => (int) $fila->id_grupo,
                'nombre' => $fila->nombre,
                'seleccion_minima' => (int) ($fila->min_plato ?? $fila->seleccion_minima),
                'seleccion_maxima' => (int) ($fila->max_plato ?? $fila->seleccion_maxima),
            ])
            ->groupBy('id_producto');
    }

    /**
     * Todas las opciones activas de los grupos, resueltas para la sucursal (incluye las no
     * disponibles, con su estado, para poder explicar un rechazo).
     *
     * @param  array<int>  $grupoIds
     * @return Collection<int, object> indexado por id_opcion
     */
    public function opciones(array $grupoIds, int $sucursalId): Collection
    {
        if ($grupoIds === []) {
            return collect();
        }

        $filas = DB::table('restaurante.opciones_modificador as o')
            ->leftJoin('restaurante.opciones_sucursal as os', function ($join) use ($sucursalId): void {
                $join->on('os.id_opcion', '=', 'o.id_opcion')->where('os.id_sucursal', '=', $sucursalId);
            })
            ->leftJoin('inventario.precio_producto as p', 'p.id_precio', '=', 'o.id_precio')
            ->leftJoin('catalogos.productos as prod', 'prod.id_producto', '=', 'p.id_producto')
            ->leftJoin('catalogos.presentaciones as pres', 'pres.id_presentacion', '=', 'p.id_presentacion')
            ->leftJoin('restaurante.productos_agotados as ag', function ($join) use ($sucursalId): void {
                $join->on('ag.id_producto', '=', 'prod.id_producto')->where('ag.id_sucursal', '=', $sucursalId);
            })
            ->whereIn('o.id_grupo', $grupoIds)
            ->where('o.activo', true)
            ->orderBy('o.orden')->orderBy('o.id_opcion')
            ->get([
                'o.id_opcion', 'o.id_grupo', 'o.nombre', 'o.id_precio', 'o.precio_adicional', 'o.orden',
                'os.precio_adicional as precio_sucursal', 'os.disponible as disponible_sucursal',
                'prod.id_producto', 'prod.nombre as nombre_producto', 'prod.tipo_producto', 'prod.estado as producto_activo',
                'p.estado as presentacion_activa', 'pres.nombre as nombre_presentacion', 'ag.id_producto as agotado',
            ]);

        $presentaciones = $this->presentacionesActivas($filas->pluck('id_producto')->filter()->unique()->values()->all());

        return $filas->map(function (object $fila) use ($presentaciones): object {
            $vinculada = $fila->id_precio !== null;
            $nombre = $fila->nombre ?? $fila->nombre_producto;
            if ($fila->nombre === null && $vinculada && ($presentaciones[$fila->id_producto] ?? 0) > 1 && $fila->nombre_presentacion) {
                $nombre .= " ({$fila->nombre_presentacion})";
            }

            $estado = self::DISPONIBLE;
            if (($fila->disponible_sucursal !== null && ! $fila->disponible_sucursal)
                || ($vinculada && (! $fila->producto_activo || ! $fila->presentacion_activa))) {
                $estado = self::NO_DISPONIBLE;
            } elseif ($vinculada && $fila->agotado !== null) {
                $estado = self::AGOTADA;
            }

            return (object) [
                'id_opcion' => (int) $fila->id_opcion,
                'id_grupo' => (int) $fila->id_grupo,
                'nombre' => (string) $nombre,
                'id_precio' => $vinculada ? (int) $fila->id_precio : null,
                'id_producto' => $vinculada ? (int) $fila->id_producto : null,
                'tipo_producto' => $fila->tipo_producto,
                'precio_adicional' => round((float) ($fila->precio_sucursal ?? $fila->precio_adicional), 2),
                'precio_general' => round((float) $fila->precio_adicional, 2),
                'precio_sucursal' => $fila->precio_sucursal !== null ? round((float) $fila->precio_sucursal, 2) : null,
                'orden' => (int) $fila->orden,
                'estado' => $estado,
            ];
        })->keyBy('id_opcion');
    }

    /**
     * Ajusta una opción en una sucursal. Recargo null + disponible = volver a lo general.
     */
    public function fijar(int $opcionId, int $sucursalId, ?float $precio, bool $disponible): void
    {
        OpcionModificador::query()->findOrFail($opcionId);
        if ($precio !== null && ($precio < 0 || $precio > 999999)) {
            throw new DomainException('El recargo debe estar entre 0 y 999 999.');
        }

        $clave = ['id_sucursal' => $sucursalId, 'id_opcion' => $opcionId];
        if ($disponible && $precio === null) {
            DB::table('restaurante.opciones_sucursal')->where($clave)->delete();

            return;
        }

        DB::table('restaurante.opciones_sucursal')->updateOrInsert($clave, [
            'precio_adicional' => $precio !== null ? round($precio, 2) : null,
            'disponible' => $disponible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, int> presentaciones activas por producto */
    private function presentacionesActivas(array $productoIds): array
    {
        if ($productoIds === []) {
            return [];
        }

        return DB::table('inventario.precio_producto')->whereIn('id_producto', $productoIds)->where('estado', true)
            ->groupBy('id_producto')->selectRaw('id_producto, COUNT(*) as total')
            ->pluck('total', 'id_producto')->map(fn ($total) => (int) $total)->all();
    }
}
