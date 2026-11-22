<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'catalogos.metodos_pago';

    /** Efectivo en gaveta: entra al arqueo de caja. */
    public const EFECTIVO = 'EFECTIVO';
    /** Yape, Plin o transferencia: llega completo a la cuenta, sin comisión. */
    public const BILLETERA_DIRECTA = 'BILLETERA_DIRECTA';
    /** Tarjeta por POS o pasarela: descuenta comisión. */
    public const PASARELA = 'PASARELA';
    public const TIPOS = [self::EFECTIVO, self::BILLETERA_DIRECTA, self::PASARELA];

    protected $primaryKey = 'id_metodo';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'requiere_referencia',
        'comision_porcentaje',
        'comision_fija',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'requiere_referencia' => 'boolean',
            'comision_porcentaje' => 'decimal:2',
            'comision_fija'       => 'decimal:2',
            'estado'              => 'boolean',
        ];
    }
}
