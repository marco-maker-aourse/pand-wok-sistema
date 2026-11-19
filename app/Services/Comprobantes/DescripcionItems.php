<?php

namespace App\Services\Comprobantes;

use Illuminate\Support\Facades\DB;

/**
 * Descripción de los ítems del comprobante para un restaurante: el plato lleva sus
 * opciones elegidas ("Lomo saltado (Jugoso, Huevo frito)"), que explican por qué el precio
 * difiere del de la carta. Se usa igual en el XML para SUNAT, el PDF y el ticket.
 * Las notas de cocina ("sin cebolla") no se incluyen: no son parte de la venta.
 */
final class DescripcionItems
{
    /** Límite prudente para cbc:Description y para una línea del ticket. */
    private const LARGO_MAXIMO = 250;

    /**
     * @param  array<int|null>  $idsDetallePedido
     * @return array<int, string> id_detalle_pedido => "Jugoso, Huevo frito"
     */
    public static function opciones(array $idsDetallePedido): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsDetallePedido))));
        if ($ids === []) {
            return [];
        }

        return DB::table('restaurante.pedido_detalle_modificadores')
            ->whereIn('id_detalle_pedido', $ids)
            ->orderBy('id_detalle_modificador')
            ->get(['id_detalle_pedido', 'nombre_opcion'])
            ->groupBy('id_detalle_pedido')
            ->map(fn ($filas) => $filas->pluck('nombre_opcion')->implode(', '))
            ->mapWithKeys(fn (string $texto, $id) => [(int) $id => $texto])
            ->all();
    }

    public static function describir(string $nombre, ?string $opciones): string
    {
        $texto = filled($opciones) ? "{$nombre} ({$opciones})" : $nombre;

        return mb_substr($texto, 0, self::LARGO_MAXIMO);
    }
}
