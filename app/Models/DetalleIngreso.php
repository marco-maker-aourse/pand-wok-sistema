<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class DetalleIngreso extends Model
{
    protected $table = 'operaciones.detalle_ingresos';
    protected $primaryKey = 'id_detalle'; // Clave primaria real según tinker
    public $timestamps = false;

    // Solo las columnas que existen en la base de datos
    protected $fillable = [
        'id_ingreso',
        'id_precio',
        'cantidad',
        'factor_conversion',
        'precio_costo',
        'subtotal',
    ];

    public function precio(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_costo' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'factor_conversion' => 'decimal:4',
        ];
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'id_ingreso', 'id_ingreso');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    /**
     * Acceso directo al producto a través de su presentación
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
