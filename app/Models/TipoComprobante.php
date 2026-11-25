<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    use HasFactory;

    protected $table = 'catalogos.tipos_comprobante';

    /** Códigos del catálogo 01 de SUNAT. */
    public const FACTURA = '01';
    public const BOLETA = '03';
    public const NOTA_CREDITO = '07';
    public const NOTA_DEBITO = '08';
    public const GUIA_REMISION = '09';
    public const TICKET_MAQUINA = '12';
    public const OTROS = '00';

    /** Comprobantes que emite el punto de venta. */
    public const EMITIDOS_EN_VENTA = [self::FACTURA, self::BOLETA];

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