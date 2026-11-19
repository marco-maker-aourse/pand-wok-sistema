<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajoImpresion extends Model
{
    use BelongsToSucursal;

    protected $table = 'operaciones.trabajos_impresion';
    protected $primaryKey = 'id_trabajo_impresion';

    protected $fillable = [
        'id_sucursal', 'id_terminal', 'id_venta', 'tipo', 'estado', 'clave_idempotencia',
        'contenido', 'intentos', 'max_intentos', 'disponible_desde', 'reclamado_en',
        'procesado_en', 'resultado_dispositivo', 'detalle_error',
    ];

    protected function casts(): array
    {
        return [
            'contenido' => 'array', 'disponible_desde' => 'datetime', 'reclamado_en' => 'datetime',
            'procesado_en' => 'datetime', 'intentos' => 'integer', 'max_intentos' => 'integer',
        ];
    }

    public function terminal(): BelongsTo { return $this->belongsTo(TerminalPos::class, 'id_terminal', 'id_terminal'); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class, 'id_venta', 'id_venta'); }
}
