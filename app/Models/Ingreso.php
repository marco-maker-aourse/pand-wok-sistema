<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingreso extends Model
{
    use BelongsToSucursal;
    // Esquema y tabla oficial de tu base de datos
    protected $table = 'operaciones.ingresos';
    protected $primaryKey = 'id_ingreso';
    public $timestamps = false;

    protected $fillable = [
        'id_sucursal',
        'id_usuario',
        'id_proveedor',
        'id_tipo_comprobante',
        'serie_comprobante',
        'num_comprobante',
        'fecha_emision',
        'impuesto_total',
        'total_ingreso',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'total_ingreso' => 'decimal:2',
            'impuesto_total' => 'decimal:2',
            'estado' => 'string',
        ];
    }

    /**
     * Relación con el Proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    /**
     * Relación con los detalles de la compra
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleIngreso::class, 'id_ingreso', 'id_ingreso');
    }

    /**
     * Relación con el tipo de comprobante
     */
    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'id_tipo_comprobante', 'id_tipo_comprobante');
    }
}
