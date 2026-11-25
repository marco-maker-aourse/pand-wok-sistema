<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatosGen extends Model
{
    protected $table = 'operaciones.datos_gen';
    protected $primaryKey = 'id_datos_gen';
    public $timestamps = false;

    protected $fillable = [
        'id_sucursal',
        'ruc_empresa',
        'razon_social',
        'nombre_comercial',
        'direccion_fiscal',
        'logo_path',
        'igv_porcentaje',
        'uit_valor',
        'serie_boleta',
        'ult_nro_boleta',
        'serie_factura',
        'ult_nro_factura',
        'serie_nota_venta',
        'ult_nro_nota_venta',
        'serie_guia',
        'ult_nro_guia',
        'pie_ticket',
        'ult_nro_entrada_interna',
        'ult_nro_salida_interna',
        // Columnas para identidad visual y textos del Login
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