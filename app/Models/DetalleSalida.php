<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class DetalleSalida extends Model
{
    protected $table = 'operaciones.detalle_salidas';
    protected $primaryKey = 'id_detalle'; // Clave primaria real según tinker
    public $timestamps = false;

    // Solo las columnas que existen físicamente en Supabase
    protected $fillable = [
        'id_salida',
        'id_precio',
        'id_lote',
        'cantidad',
    ];

    public function precio(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class, 'id_salida', 'id_salida');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    /**
     * Acceso directo al producto para vistas y reportes
     */
    public function producto(): HasOneThrough
    {
        return $this->hasOneThrough(
            Producto::class,
            ProductoPresentacion::class,
            'id_precio',
            'id_producto',
            'id_precio',
            'id_producto'
        );
    }
}
