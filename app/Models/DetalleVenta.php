<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends Model
{
    protected $table = 'operaciones.detalle_ventas';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_venta', 'id_precio', 'cantidad', 'precio_unitario', 'subtotal', 'id_detalle_pedido',
    ];

    public function precio(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }
}
