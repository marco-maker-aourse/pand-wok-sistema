<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

/** Historial de solo inserción de un pedido. */
class PedidoEvento extends Model
{
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $table = 'restaurante.pedido_eventos';
    protected $primaryKey = 'id_evento';
    protected $fillable = ['id_pedido', 'id_detalle_pedido', 'id_usuario', 'tipo', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array', 'creado_en' => 'datetime'];
    }
}
