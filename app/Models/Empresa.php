<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos fiscales e identidad visual de la empresa. Hay una sola fila: todas las
 * sucursales emiten con este RUC; la numeración de cada una está en series_sucursal.
 */
class Empresa extends Model
{
    protected $table = 'configuracion.empresa';

    protected $primaryKey = 'id_empresa';

    protected $fillable = [
        'ruc_empresa',
        'razon_social',
        'nombre_comercial',
        'direccion_fiscal',
        'logo_path',
        'igv_porcentaje',
        'uit_valor',
        'pie_ticket',
        // Identidad visual y textos del inicio de sesión.
        'color_primario',
        'color_secundario',
        'texto_login_titulo',
        'texto_login_descripcion',
    ];

    protected function casts(): array
    {
        return [
            'igv_porcentaje' => 'decimal:2',
            'uit_valor'      => 'decimal:2',
        ];
    }
}
