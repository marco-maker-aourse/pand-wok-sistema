<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoCaja extends Model
{
    use BelongsToSucursal;

    protected $table = 'operaciones.turnos_caja';

    protected $primaryKey = 'id_turno';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario', 'id_sucursal', 'id_terminal', 'monto_apertura', 'monto_cierre_declarado',
        'fecha_apertura', 'fecha_cierre', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto_apertura' => 'decimal:2',
            'monto_cierre_declarado' => 'decimal:2',
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo { return $this->belongsTo(TerminalPos::class, 'id_terminal', 'id_terminal'); }
}
