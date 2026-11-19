<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'catalogos.tipos_documento';
    protected $primaryKey = 'id_tipo_documento';
    public $timestamps = false;

    protected $fillable = [
        'codigo_sunat',
        'nombre',
        'longitud_exacta',
        'tipo_persona',
        'estado',
    ];

    protected function casts(): array
    {
        return ['longitud_exacta' => 'integer', 'estado' => 'boolean'];
    }
}
