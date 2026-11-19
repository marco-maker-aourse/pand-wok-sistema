<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class PedidoDetalleModificador extends Model
{
    protected $table = 'restaurante.pedido_detalle_modificadores';
    protected $primaryKey = 'id_detalle_modificador';
    public $timestamps = false;
    protected $fillable = ['id_detalle_pedido', 'id_opcion', 'nombre_grupo', 'nombre_opcion', 'precio_adicional'];

    protected function casts(): array
    {
        return ['precio_adicional' => 'decimal:2'];
    }
}
