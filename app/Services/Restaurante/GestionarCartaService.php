<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\GrupoModificador;
use App\Models\Restaurante\OpcionModificador;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Ajustes de carta propios del restaurante sobre el catálogo de productos existente. */
class GestionarCartaService
{
    public function cambiarTipo(int $productoId, string $tipo): Producto
    {
        if (! in_array($tipo, Producto::TIPOS, true)) {
            throw new DomainException('Tipo de producto no válido.');
        }

        $producto = Producto::query()->findOrFail($productoId);
        $producto->update(['tipo_producto' => $tipo]);

        return $producto;
    }

    /** Marca o desmarca un producto como agotado solo en la sucursal indicada. */
    public function marcarAgotado(int $productoId, bool $agotado, int $usuarioId, int $sucursalId): void
    {
        Producto::query()->findOrFail($productoId);
        $clave = ['id_sucursal' => $sucursalId, 'id_producto' => $productoId];

        if ($agotado) {
            DB::table('restaurante.productos_agotados')->insertOrIgnore([...$clave, 'id_usuario' => $usuarioId, 'marcado_en' => now()]);

            return;
        }

        DB::table('restaurante.productos_agotados')->where($clave)->delete();
    }

    /** @return array<int> ids de productos agotados en la sucursal */
    public function agotados(int $sucursalId): array
    {
        return DB::table('restaurante.productos_agotados')->where('id_sucursal', $sucursalId)->pluck('id_producto')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Reemplaza los grupos de modificadores de un plato; el orden del arreglo es el
     * orden en que el mozo los verá. $reglas ajusta la obligatoriedad solo para este plato
     * (id_grupo => [seleccion_minima, seleccion_maxima], null = la del grupo).
     *
     * @param  array<int>  $grupoIds
     * @param  array<int, array{seleccion_minima?:?int, seleccion_maxima?:?int}>  $reglas
     */
    public function asignarGrupos(int $productoId, array $grupoIds, array $reglas = []): void
    {
        $grupoIds = array_values(array_unique(array_map('intval', $grupoIds)));
        $producto = Producto::query()->findOrFail($productoId);

        if ($producto->tipo_producto === Producto::INSUMO && $grupoIds !== []) {
            throw new DomainException('Un insumo no lleva modificadores.');
        }

        $existentes = GrupoModificador::query()->whereKey($grupoIds)->count();
        if ($existentes !== count($grupoIds)) {
            throw new DomainException('Alguno de los grupos de modificadores no existe.');
        }

        $sincronizar = [];
        foreach ($grupoIds as $orden => $grupoId) {
            $minimo = $reglas[$grupoId]['seleccion_minima'] ?? null;
            $maximo = $reglas[$grupoId]['seleccion_maxima'] ?? null;
            if (($maximo !== null && $maximo < 1) || ($minimo !== null && $maximo !== null && $minimo > $maximo)) {
                throw new DomainException('El mínimo no puede superar al máximo y el máximo debe ser al menos 1.');
            }
            $sincronizar[$grupoId] = ['orden' => $orden, 'seleccion_minima' => $minimo, 'seleccion_maxima' => $maximo];
        }
        $producto->gruposModificadores()->sync($sincronizar);
    }

    /**
     * Crea o actualiza una opción. Si se vincula a un producto de la carta (sopa, acompañamiento)
     * no necesita nombre propio: se muestra el del producto y se descuenta su receta o stock.
     *
     * @param  array{nombre?:?string, id_precio?:?int, precio_adicional:float|string, orden?:?int, activo?:bool}  $datos
     */
    public function guardarOpcion(?OpcionModificador $opcion, ?int $grupoId, array $datos): OpcionModificador
    {
        $grupoId = $opcion?->id_grupo ?? $grupoId;
        $nombre = filled($datos['nombre'] ?? null) ? trim($datos['nombre']) : null;
        $idPrecio = isset($datos['id_precio']) ? (int) $datos['id_precio'] : null;

        if ($idPrecio !== null) {
            $precio = ProductoPresentacion::query()->with('producto')->where('estado', true)->find($idPrecio);
            if (! $precio || ! $precio->producto?->estado || $precio->producto->tipo_producto === Producto::INSUMO) {
                throw new DomainException('Vincula la opción a un plato o bebida activo de la carta (no a un insumo).');
            }
            $repetida = OpcionModificador::query()->where('id_grupo', $grupoId)->where('id_precio', $idPrecio)
                ->when($opcion, fn ($q) => $q->whereKeyNot($opcion->id_opcion))->exists();
            if ($repetida) {
                throw new DomainException('Ese producto ya es una opción del grupo.');
            }
        } elseif ($nombre === null) {
            throw new DomainException('Escribe un nombre o vincula la opción a un producto.');
        }

        $valores = [
            'nombre' => $nombre, 'id_precio' => $idPrecio,
            'precio_adicional' => round((float) $datos['precio_adicional'], 2),
            'orden' => (int) ($datos['orden'] ?? 0), 'activo' => (bool) ($datos['activo'] ?? true),
        ];
        if ($opcion) {
            $opcion->update($valores);

            return $opcion;
        }

        return OpcionModificador::query()->create([...$valores, 'id_grupo' => $grupoId]);
    }

    /**
     * Declara que una presentación es una variante física con stock propio (Inca Kola 1 L)
     * o la devuelve al inventario base de su producto (null).
     */
    public function fijarVariante(int $precioId, ?int $productoStockId): void
    {
        $precio = ProductoPresentacion::query()->with('producto')->findOrFail($precioId);
        if ($productoStockId !== null) {
            $stock = Producto::query()->where('estado', true)->find($productoStockId);
            if (! $stock || $stock->tipo_producto !== Producto::INSUMO || $stock->es_servicio || $stock->id_producto === $precio->id_producto) {
                throw new DomainException('La existencia de la variante debe ser un insumo activo con stock (p. ej. «Inca Kola botella 1 L»).');
            }
            if ($precio->producto?->tipo_producto !== Producto::DIRECTO || $precio->producto->es_servicio) {
                throw new DomainException('Solo un producto que se vende tal cual puede tener variantes con stock propio.');
            }
        }

        $precio->update(['id_producto_stock' => $productoStockId]);
    }
}
