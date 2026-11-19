<?php

namespace App\Models\Restaurante;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ambiente del local (salón, terraza, barra) de una sucursal. */
class Area extends Model
{
    use BelongsToSucursal;

    protected $table = 'restaurante.areas';
    protected $primaryKey = 'id_area';
    protected $fillable = ['id_sucursal', 'nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'id_area', 'id_area');
    }
}
