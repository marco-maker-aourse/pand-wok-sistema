<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoPresentacion extends Model
{
    /** Precio por presentación; conserva el nombre PHP para no romper el frontend actual. */
    protected $table = 'catalogos.producto_presentaciones';
    protected $primaryKey = 'id_precio';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_presentacion',
        'factor_conversion',
        'precio_venta',
        'estado',
        'id_producto_stock',
    ];

    /** Mantiene el contrato de pantallas ya construidas mientras la UI adopta id_precio. */
    protected $appends = ['id_prod_pres'];

    protected function casts(): array
    {
        return [
            'factor_conversion'   => 'decimal:4',
            'precio_venta'        => 'decimal:2',
            'estado'              => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    /**
     * Variante física con stock propio (Inca Kola 1 L): al venderla se descuenta este
     * producto de inventario. Sin él, se descuenta el inventario base del producto.
     */
    public function productoStock(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto_stock', 'id_producto');
    }

    /** Producto cuyo inventario se mueve al vender esta presentación. */
    public function productoInventario(): ?Producto
    {
        return $this->id_producto_stock ? $this->productoStock : $this->producto;
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class, 'id_presentacion', 'id_presentacion');
    }

    /** Alias temporal para pantallas aún no migradas al nombre físico id_precio. */
    public function getIdProdPresAttribute(): int
    {
        return $this->id_precio;
    }
}
