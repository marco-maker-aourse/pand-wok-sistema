<?php

namespace Tests\Feature\Restaurante;

use Illuminate\Support\Facades\DB;

/**
 * Carta con menú, sopas, combinación y acompañamientos sobre la base de operación.
 * Los precios son de prueba, no de la carta real.
 *
 * - Menú arroz chaufa (precio 10, S/ 15), grupo "Sopa del menú" obligatorio (1 por menú).
 * - Sopas (productos 11-14, precios 11-14): pollo, kion, wantán, patita; incluidas (recargo 0).
 * - Combinación chaufa + tallarín (precio 20, S/ 18), grupo "Acompañamientos" (0 a 2):
 *   Wantán frito x3 (producto 21, recargo general S/ 3) y Papa frita (sin producto, incluida).
 * - Insumos: Pollo (30, kg) con 10 en stock y Masa wantán (31, und) con 50 en la sucursal 1.
 * - Recetas sucursal 1: menú 0.2 kg de pollo; sopa de pollo 0.1 kg de pollo; sopa wantán 3 masas;
 *   wantán frito 3 masas.
 */
trait PreparaCartaEstructurada
{
    use PreparaOperacionRestaurante;

    protected function prepararCartaEstructurada(): void
    {
        $this->prepararOperacionRestaurante();

        DB::table('catalogos.productos')->insert([
            ['id_producto' => 10, 'nombre' => 'Menú arroz chaufa', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 11, 'nombre' => 'Sopa de pollo', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 12, 'nombre' => 'Sopa de kion', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 13, 'nombre' => 'Sopa wantán', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 14, 'nombre' => 'Sopa de patita', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 20, 'nombre' => 'Combinación chaufa + tallarín', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 21, 'nombre' => 'Wantán frito x3', 'tipo_producto' => 'PREPARADO'],
            ['id_producto' => 30, 'nombre' => 'Pollo', 'tipo_producto' => 'INSUMO'],
            ['id_producto' => 31, 'nombre' => 'Masa wantán', 'tipo_producto' => 'INSUMO'],
        ]);
        DB::table('inventario.precio_producto')->insert([
            ['id_precio' => 10, 'id_producto' => 10, 'id_presentacion' => 1, 'precio_venta' => 15],
            ['id_precio' => 11, 'id_producto' => 11, 'id_presentacion' => 1, 'precio_venta' => 8],
            ['id_precio' => 12, 'id_producto' => 12, 'id_presentacion' => 1, 'precio_venta' => 8],
            ['id_precio' => 13, 'id_producto' => 13, 'id_presentacion' => 1, 'precio_venta' => 9],
            ['id_precio' => 14, 'id_producto' => 14, 'id_presentacion' => 1, 'precio_venta' => 9],
            ['id_precio' => 20, 'id_producto' => 20, 'id_presentacion' => 1, 'precio_venta' => 18],
            ['id_precio' => 21, 'id_producto' => 21, 'id_presentacion' => 1, 'precio_venta' => 6],
            ['id_precio' => 30, 'id_producto' => 30, 'id_presentacion' => 1, 'precio_venta' => 0],
            ['id_precio' => 31, 'id_producto' => 31, 'id_presentacion' => 1, 'precio_venta' => 0],
        ]);

        DB::table('restaurante.grupos_modificadores')->insert([
            ['id_grupo' => 10, 'nombre' => 'Sopa del menú', 'seleccion_minima' => 1, 'seleccion_maxima' => 1],
            ['id_grupo' => 11, 'nombre' => 'Acompañamientos', 'seleccion_minima' => 0, 'seleccion_maxima' => 2],
        ]);
        // Las sopas no repiten su nombre: lo toman del producto vinculado.
        DB::table('restaurante.opciones_modificador')->insert([
            ['id_opcion' => 11, 'id_grupo' => 10, 'nombre' => null, 'id_precio' => 11, 'precio_adicional' => 0, 'orden' => 1],
            ['id_opcion' => 12, 'id_grupo' => 10, 'nombre' => null, 'id_precio' => 12, 'precio_adicional' => 0, 'orden' => 2],
            ['id_opcion' => 13, 'id_grupo' => 10, 'nombre' => null, 'id_precio' => 13, 'precio_adicional' => 0, 'orden' => 3],
            ['id_opcion' => 14, 'id_grupo' => 10, 'nombre' => null, 'id_precio' => 14, 'precio_adicional' => 0, 'orden' => 4],
            ['id_opcion' => 21, 'id_grupo' => 11, 'nombre' => null, 'id_precio' => 21, 'precio_adicional' => 3, 'orden' => 1],
            ['id_opcion' => 22, 'id_grupo' => 11, 'nombre' => 'Papa frita', 'id_precio' => null, 'precio_adicional' => 0, 'orden' => 2],
        ]);
        DB::table('restaurante.producto_grupo_modificador')->insert([
            ['id_producto' => 10, 'id_grupo' => 10, 'orden' => 0],
            ['id_producto' => 20, 'id_grupo' => 11, 'orden' => 0],
        ]);

        DB::table('restaurante.receta_insumos')->insert([
            ['id_sucursal' => 1, 'id_precio_plato' => 10, 'id_precio_insumo' => 30, 'cantidad' => 0.2],
            ['id_sucursal' => 1, 'id_precio_plato' => 11, 'id_precio_insumo' => 30, 'cantidad' => 0.1],
            ['id_sucursal' => 1, 'id_precio_plato' => 13, 'id_precio_insumo' => 31, 'cantidad' => 3],
            ['id_sucursal' => 1, 'id_precio_plato' => 21, 'id_precio_insumo' => 31, 'cantidad' => 3],
        ]);
        DB::table('inventario.kardex')->insert([
            ['id_sucursal' => 1, 'id_producto' => 30, 'stock_actual' => 10, 'precio_costo' => 12],
            ['id_sucursal' => 1, 'id_producto' => 31, 'stock_actual' => 50, 'precio_costo' => 0.2],
        ]);
    }

    protected function menu(string $clave, float $cantidad = 1, array $sopas = [13 => 1]): array
    {
        return ['clave' => $clave, 'id_precio' => 10, 'cantidad' => $cantidad,
            'selecciones' => collect($sopas)->map(fn ($c, $id) => ['id_opcion' => $id, 'cantidad' => $c])->values()->all()];
    }

    protected function combinacion(string $clave, float $cantidad = 1, array $acompanamientos = []): array
    {
        return ['clave' => $clave, 'id_precio' => 20, 'cantidad' => $cantidad,
            'selecciones' => collect($acompanamientos)->map(fn ($c, $id) => ['id_opcion' => $id, 'cantidad' => $c])->values()->all()];
    }
}
