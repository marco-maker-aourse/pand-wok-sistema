<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Model
{
    protected $table = 'catalogos.unidades_medida';

    /** Códigos del catálogo 03 de SUNAT usados por el propio sistema. */
    public const UNIDAD = 'NIU';
    public const SERVICIO = 'ZZ';

    protected $primaryKey = 'id_unidad';
    public $timestamps = false;

    protected $fillable = ['codigo_sunat', 'nombre', 'estado'];

    public function productos(): HasMany { return $this->hasMany(Producto::class, 'id_unidad_base', 'id_unidad'); }
}
