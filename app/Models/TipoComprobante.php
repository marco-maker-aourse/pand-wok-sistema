<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    use HasFactory;

    protected $table = 'catalogos.tipos_comprobante';
    protected $primaryKey = 'id_tipo_comprobante';
    public $timestamps = false;

    protected $fillable = [
        'codigo_sunat',
        'descripcion',
        'exige_ruc',
        'permite_anulacion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'exige_ruc'         => 'boolean',
            'permite_anulacion' => 'boolean',
            'estado'            => 'boolean',
        ];
    }
}