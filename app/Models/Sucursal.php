<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    protected $table = 'inventario.sucursales';

    protected $primaryKey = 'id_sucursal';

    public $timestamps = false;

    protected $fillable = [
        'id_ubigeo',
        'nombre',
        'direccion',
        'telefono',
        'responsable',
        'codigo_establecimiento',
        'estado',
        // Carta pública: identificador estable en la URL (p. ej. "chilca") y horario visible.
        'slug',
        'horario_atencion',
    ];

    public function usuarios(): HasMany { return $this->hasMany(User::class, 'id_sucursal', 'id_sucursal'); }
    public function impresoras(): HasMany { return $this->hasMany(Impresora::class, 'id_sucursal', 'id_sucursal'); }
    public function terminalesPos(): HasMany { return $this->hasMany(TerminalPos::class, 'id_sucursal', 'id_sucursal'); }
}
