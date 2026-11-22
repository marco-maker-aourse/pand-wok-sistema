<?php

namespace App\Services\Restaurante;

use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\GrupoModificador;
use App\Models\Restaurante\OpcionModificador;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deja listo el grupo "Sopa del menú" con las sopas iniciales (pollo, kion, wantán, patita).
 *
 * Idempotente y sin duplicar nombres:
 * - Cada sopa se busca entre los productos existentes (sin distinguir mayúsculas ni tildes,
 *   p. ej. "Sopa Wantan" o "Sopa de Patitas (Sáb. Dom.)"). Solo si no existe se crea como plato
 *   preparado en "Sopas", con precio 0 y sin venderse sola en ninguna sucursal (no se inventan
 *   precios: se fijan después en la carta).
 * - Las opciones del grupo se vinculan al producto: no repiten su nombre y su receta se descuenta.
 * - Con $menus, el grupo se agrega a esos platos conservando sus otros grupos.
 */
class ConfigurarSopasMenuService
{
    public const GRUPO = 'Sopa del menú';

    /** Nombre con el que se crea la sopa si no existe => prefijo normalizado para buscarla. */
    public const SOPAS = [
        'Sopa de pollo' => 'sopa de pollo',
        'Sopa de kion' => 'sopa de kion',
        'Sopa wantán' => 'sopa wantan',
        'Sopa de patita' => 'sopa de patita',
    ];

    /** @return array{grupo:int, reutilizadas:array<string>, creadas:array<string>, menus:int} */
    public function ejecutar(array $menus = []): array
    {
        return DB::transaction(function () use ($menus): array {
            $grupo = GrupoModificador::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower(self::GRUPO)])->first()
                ?? GrupoModificador::query()->create(['nombre' => self::GRUPO, 'seleccion_minima' => 1, 'seleccion_maxima' => 1, 'activo' => true]);

            $existentes = Producto::query()->where('tipo_producto', '!=', Producto::INSUMO)->get(['id_producto', 'nombre']);
            $reutilizadas = [];
            $creadas = [];
            $orden = 0;
            foreach (self::SOPAS as $nombre => $prefijo) {
                $producto = $existentes->first(fn (Producto $p) => str_starts_with(self::normalizar($p->nombre), $prefijo));
                $producto ? $reutilizadas[] = $producto->nombre : $creadas[] = $nombre;
                $precio = $producto
                    ? ProductoPresentacion::query()->where('id_producto', $producto->id_producto)->where('estado', true)->orderBy('factor_conversion')->first()
                    : $this->crearSopa($nombre);
                if (! $precio) {
                    continue;
                }

                OpcionModificador::query()->firstOrCreate(
                    ['id_grupo' => $grupo->id_grupo, 'id_precio' => $precio->id_precio],
                    ['nombre' => null, 'precio_adicional' => 0, 'orden' => ++$orden, 'activo' => true],
                );
            }

            foreach (array_unique(array_map('intval', $menus)) as $productoId) {
                $siguiente = (int) DB::table('restaurante.producto_grupo_modificador')->where('id_producto', $productoId)->max('orden') + 1;
                DB::table('restaurante.producto_grupo_modificador')->insertOrIgnore([
                    'id_producto' => $productoId, 'id_grupo' => $grupo->id_grupo, 'orden' => $siguiente,
                ]);
            }

            return ['grupo' => $grupo->id_grupo, 'reutilizadas' => $reutilizadas, 'creadas' => $creadas, 'menus' => count($menus)];
        });
    }

    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->value();
    }

    private function crearSopa(string $nombre): ProductoPresentacion
    {
        $categoria = Categoria::query()->whereRaw('LOWER(nombre) = ?', ['sopas'])->first()
            ?? Categoria::query()->create(['nombre' => 'Sopas', 'estado' => true]);
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo_sunat' => 'NIU'], ['nombre' => 'Unidad', 'estado' => true]);
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

        // Sin precio real todavía: no se vende sola en ninguna sucursal, solo como opción del menú.
        foreach (DB::table('configuracion.sucursales')->pluck('id_sucursal') as $sucursal) {
            DB::table('restaurante.precios_sucursal')->insertOrIgnore([
                'id_sucursal' => $sucursal, 'id_precio' => $precio->id_precio, 'precio_venta' => null, 'disponible' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $precio;
    }
}
