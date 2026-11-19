<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'catalogos.metodos_pago';
    protected $primaryKey = 'id_metodo';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'requiere_referencia',
        'comision_porcentaje',
        'comision_fija',
    ];

    protected function casts(): array
    {
        return [
            'requiere_referencia' => 'boolean',
            'comision_porcentaje' => 'decimal:2',
            'comision_fija'       => 'decimal:2',
        ];
    }
}
