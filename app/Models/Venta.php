<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venta extends Model
{
    use HasFactory, BelongsToSucursal;

    protected $table = 'operaciones.ventas';
    protected $primaryKey = 'id_venta';

    public $timestamps = false;

    protected $fillable = [
        'id_sucursal', 'id_cliente', 'id_usuario', 'id_turno', 'id_tipo_comprobante',
        'serie', 'correlativo', 'fecha_emision', 'op_gravadas', 
        'op_exoneradas', 'igv', 'descuento_global', 'total_venta', 
        'estado_sunat', 'hash_cpe', 'enlace_pdf', 'enlace_xml', 'enlace_cdr'
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'op_gravadas' => 'decimal:2',
            'op_exoneradas' => 'decimal:2',
            'igv' => 'decimal:2',
            'descuento_global' => 'decimal:2',
            'total_venta' => 'decimal:2',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoVenta::class, 'id_venta', 'id_venta');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'id_tipo_comprobante', 'id_tipo_comprobante');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'id_turno', 'id_turno');
    }
}
