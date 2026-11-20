<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class PedidoDetalleModificador extends Model
{
    protected $table = 'restaurante.pedido_detalle_modificadores';
    protected $primaryKey = 'id_detalle_modificador';
    public $timestamps = false;
    protected $fillable = ['id_detalle_pedido', 'id_opcion', 'nombre_grupo', 'nombre_opcion', 'precio_adicional', 'cantidad', 'id_precio'];

    protected function casts(): array
    {
        return ['precio_adicional' => 'decimal:2', 'cantidad' => 'decimal:3'];
    }

    /**
     * Texto para cocina y tickets. Si todas las unidades llevan la opción basta el nombre;
     * si se repartió entre unidades se indica cuántas: "1 × Sopa wantán".
     */
    public function etiqueta(float $cantidadItem): string
    {
        return self::etiquetaDe($this->nombre_opcion, (float) $this->cantidad, $cantidadItem);
    }

    public static function etiquetaDe(string $nombre, float $cantidad, float $cantidadItem): string
    {
        $cantidad = round($cantidad, 3);
        if ($cantidad === round($cantidadItem, 3)) {
            return $nombre;
        }

        return rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.').' × '.$nombre;
    }
}
