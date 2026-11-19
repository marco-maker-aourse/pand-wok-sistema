<?php

namespace App\Models\Restaurante;

use App\Models\Concerns\BelongsToSucursal;
use App\Models\Impresora;
use App\Models\TerminalPos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Estación de preparación (cocina caliente, fría, bar…) de una sucursal. */
class Estacion extends Model
{
    use BelongsToSucursal;

    protected $table = 'restaurante.estaciones';
    protected $primaryKey = 'id_estacion';
    protected $fillable = ['id_sucursal', 'nombre', 'id_impresora', 'id_terminal', 'copias', 'por_defecto', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['por_defecto' => 'boolean', 'activo' => 'boolean', 'copias' => 'integer', 'orden' => 'integer'];
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class, 'id_impresora', 'id_impresora');
    }

    /** Terminal cuyo agente envía la comanda a la impresora de la estación. */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalPos::class, 'id_terminal', 'id_terminal');
    }
}
