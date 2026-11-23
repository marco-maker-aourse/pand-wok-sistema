<?php

namespace App\Support\Restaurante;

use App\Models\Producto;
use Illuminate\Support\Str;

/**
 * Etiqueta que distingue platos parecidos de distintas categorías ("Menú", "Combinación").
 * Las categorías se configuran en config/restaurante.php.
 */
final class DistintivoCategoria
{
    public static function deCategoria(?string $categoria): ?string
    {
        if ($categoria === null) {
            return null;
        }

        return config('restaurante.categorias_distintivas')[Str::of($categoria)->ascii()->lower()->squish()->value()] ?? null;
    }

    public static function deProducto(?Producto $producto): ?string
    {
        return self::deCategoria($producto?->categoria?->nombre);
    }

    /** Nombre para medios de solo texto (comanda impresa): "Menú: Chaufa c/Lomo". */
    public static function nombreImpreso(string $nombre, ?string $distintivo): string
    {
        return $distintivo ? "{$distintivo}: {$nombre}" : $nombre;
    }
}
