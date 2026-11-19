<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoVenta extends Model
{
    protected $table = 'operaciones.pagos_ventas';
    protected $primaryKey = 'id_pago';
    public $timestamps = false;

    protected $fillable = [
        'id_venta', 'id_metodo', 'monto', 'monto_recibido', 'vuelto', 'nro_referencia', 'fecha_pago',
        'monto_neto', 'comision_pasarela', 'estado_pago',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'datetime',
            'monto' => 'decimal:2',
            'monto_recibido' => 'decimal:2',
            'vuelto' => 'decimal:2',
            'monto_neto' => 'decimal:2',
            'comision_pasarela' => 'decimal:2',
        ];
    }

    public function metodo(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo', 'id_metodo');
    }
}
