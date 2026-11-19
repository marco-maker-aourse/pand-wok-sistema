<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lote extends Model
{
    use BelongsToSucursal;
    protected $table = 'inventario.lotes';
    protected $primaryKey = 'id_lote';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_sucursal',
        'codigo_lote',
        'fecha_vencimiento',
        'stock',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'stock'             => 'decimal:4',
            'fecha_vencimiento' => 'date',
            'estado'            => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
