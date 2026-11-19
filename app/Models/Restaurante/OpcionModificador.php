<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcionModificador extends Model
{
    protected $table = 'restaurante.opciones_modificador';
    protected $primaryKey = 'id_opcion';
    protected $fillable = ['id_grupo', 'nombre', 'precio_adicional', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'precio_adicional' => 'decimal:2', 'orden' => 'integer'];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoModificador::class, 'id_grupo', 'id_grupo');
    }
}
