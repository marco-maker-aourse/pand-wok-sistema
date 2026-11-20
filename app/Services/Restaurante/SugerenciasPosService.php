<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\PedidoDetalle;
use Illuminate\Support\Facades\DB;

/**
 * Sugerencias del POS calculadas solo con pedidos reales de la sucursal (sin IA ni cifras
 * inventadas). Si no hay historial, las listas salen vacías y el POS no muestra nada.
 *
 * - frecuentes: productos más pedidos (por unidades) en los últimos 30 días.
 * - ultimos: lo último que pidió este usuario, sin repetir.
 * - acompanan: por producto, los que más veces aparecieron en la misma cuenta (60 días).
 */
class SugerenciasPosService
{
    private const LIMITE = 8;

    /** @return array{frecuentes: array<int>, ultimos: array<int>, acompanan: array<int, array<int>>} */
    public function calcular(int $sucursalId, int $usuarioId): array
    {
        return [
            'frecuentes' => $this->frecuentes($sucursalId),
            'ultimos' => $this->ultimos($sucursalId, $usuarioId),
            'acompanan' => $this->acompanan($sucursalId),
        ];
    }

    private function base(int $sucursalId, int $dias)
    {
        return DB::table('restaurante.pedido_detalles as d')
            ->join('restaurante.pedidos as p', 'p.id_pedido', '=', 'd.id_pedido')
            ->where('p.id_sucursal', $sucursalId)
            ->where('d.estado', '!=', PedidoDetalle::ANULADO)
            ->where('p.abierto_en', '>=', now()->subDays($dias));
    }

    /** @return array<int> */
    private function frecuentes(int $sucursalId): array
    {
        return $this->base($sucursalId, 30)
            ->groupBy('d.id_producto')
            ->orderByRaw('SUM(d.cantidad) DESC')->orderBy('d.id_producto')
            ->limit(self::LIMITE)
            ->pluck('d.id_producto')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int> */
    private function ultimos(int $sucursalId, int $usuarioId): array
    {
        return $this->base($sucursalId, 30)
            ->where('d.id_usuario', $usuarioId)
            ->groupBy('d.id_producto')
            ->orderByRaw('MAX(d.id_detalle_pedido) DESC')
            ->limit(self::LIMITE)
            ->pluck('d.id_producto')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, array<int>> id_producto => hasta 3 productos que suelen acompañarlo */
    private function acompanan(int $sucursalId): array
    {
        // Se limita a las cuentas recientes para que la consulta siga siendo barata.
        $pedidos = DB::table('restaurante.pedidos')->where('id_sucursal', $sucursalId)
            ->where('abierto_en', '>=', now()->subDays(60))
            ->orderByDesc('id_pedido')->limit(2000)->pluck('id_pedido');
        if ($pedidos->isEmpty()) {
            return [];
        }

        $pares = DB::table('restaurante.pedido_detalles as a')
            ->join('restaurante.pedido_detalles as b', function ($join): void {
                $join->on('b.id_pedido', '=', 'a.id_pedido')->on('b.id_producto', '<>', 'a.id_producto');
            })
            ->whereIn('a.id_pedido', $pedidos)
            ->where('a.estado', '!=', PedidoDetalle::ANULADO)
            ->where('b.estado', '!=', PedidoDetalle::ANULADO)
            ->groupBy('a.id_producto', 'b.id_producto')
            ->selectRaw('a.id_producto as producto, b.id_producto as otro, COUNT(DISTINCT a.id_pedido) as veces')
            ->get();

        return $pares->groupBy('producto')
            ->map(fn ($filas) => $filas->sortBy([['veces', 'desc'], ['otro', 'asc']])->take(3)->pluck('otro')->map(fn ($id) => (int) $id)->values()->all())
            ->mapWithKeys(fn ($otros, $producto) => [(int) $producto => $otros])
            ->all();
    }
}
