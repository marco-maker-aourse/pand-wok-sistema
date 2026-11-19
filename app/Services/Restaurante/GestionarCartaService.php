<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\Restaurante\GrupoModificador;
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
     * orden en que el mozo los verá.
     *
     * @param  array<int>  $grupoIds
     */
    public function asignarGrupos(int $productoId, array $grupoIds): void
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
            $sincronizar[$grupoId] = ['orden' => $orden];
        }
        $producto->gruposModificadores()->sync($sincronizar);
    }
}
