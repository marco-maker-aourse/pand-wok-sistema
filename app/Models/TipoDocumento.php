<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'catalogos.tipos_documento';

    /** Códigos del catálogo 06 de SUNAT. */
    public const SIN_DOCUMENTO = '0';
    public const DNI = '1';
    public const CARNET_EXTRANJERIA = '4';
    public const RUC = '6';
    public const PASAPORTE = '7';

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
