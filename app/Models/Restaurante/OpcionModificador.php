<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcionModificador extends Model
{
    protected $table = 'restaurante.opciones_modificador';
    protected $primaryKey = 'id_opcion';
    protected $fillable = ['id_grupo', 'nombre', 'id_precio', 'precio_adicional', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'precio_adicional' => 'decimal:2', 'orden' => 'integer'];
    }

    /** Producto de la carta que representa la opción (sopa, acompañamiento); su nombre se usa si la opción no tiene uno propio. */
    public function precio(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoModificador::class, 'id_grupo', 'id_grupo');
    }
}
