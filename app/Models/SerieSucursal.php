<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Series y correlativos de comprobantes de una sucursal (única fuente de numeración). */
class SerieSucursal extends Model
{
    protected $table = 'configuracion.series_sucursal';

    protected $primaryKey = 'id_sucursal';

    public $incrementing = false;

    protected $fillable = ['id_sucursal', 'serie_boleta', 'ult_nro_boleta', 'serie_factura', 'ult_nro_factura', 'serie_guia'];

    protected function casts(): array
    {
        return ['ult_nro_boleta' => 'integer', 'ult_nro_factura' => 'integer'];
    }
}
