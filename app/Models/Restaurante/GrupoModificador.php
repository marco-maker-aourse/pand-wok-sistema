<?php

namespace App\Models\Restaurante;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grupo de opciones de un plato ("Término", "Adicionales"), compartido entre sucursales. */
class GrupoModificador extends Model
{
    protected $table = 'restaurante.grupos_modificadores';
    protected $primaryKey = 'id_grupo';
    protected $fillable = ['nombre', 'seleccion_minima', 'seleccion_maxima', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'seleccion_minima' => 'integer', 'seleccion_maxima' => 'integer'];
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionModificador::class, 'id_grupo', 'id_grupo');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'restaurante.producto_grupo_modificador', 'id_grupo', 'id_producto')
            ->withPivot('orden');
    }
}
