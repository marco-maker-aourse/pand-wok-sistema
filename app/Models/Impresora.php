<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impresora extends Model
{
    use BelongsToSucursal;

    protected $table = 'catalogos.impresoras';
    protected $primaryKey = 'id_impresora';

    protected $fillable = [
        'id_sucursal', 'nombre', 'marca', 'modelo', 'tipo_conexion', 'identificador',
        'puerto', 'ancho_papel_mm', 'soporta_escpos', 'corte_automatico', 'abre_cajon',
        'activo', 'estado_conexion', 'ultima_verificacion', 'ultimo_error_conexion',
        'ultima_impresion', 'ultimo_resultado_impresion', 'ultimo_error_impresion',
    ];

    protected function casts(): array
    {
        return [
            'puerto' => 'integer', 'ancho_papel_mm' => 'integer',
            'soporta_escpos' => 'boolean', 'corte_automatico' => 'boolean',
            'abre_cajon' => 'boolean', 'activo' => 'boolean',
            'ultima_verificacion' => 'datetime', 'ultima_impresion' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal'); }
    public function terminales(): HasMany { return $this->hasMany(TerminalPos::class, 'id_impresora', 'id_impresora'); }
}
