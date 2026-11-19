<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    protected $table = 'operaciones.movimientos_caja';

    protected $primaryKey = 'id_movimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_turno',
        'id_usuario',
        'tipo_movimiento',
        'monto',
        'motivo',
        'fecha_movimiento',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_movimiento' => 'datetime',
        ];
    }
}
