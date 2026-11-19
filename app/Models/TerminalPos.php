<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalPos extends Model
{
    use BelongsToSucursal;

    protected $table = 'catalogos.terminales_pos';
    protected $primaryKey = 'id_terminal';

    protected $fillable = [
        'id_sucursal', 'id_impresora', 'codigo', 'nombre', 'identificador_equipo', 'impresora_sistema_nombre',
        'impresion_automatica', 'copias_ticket', 'activo', 'token_agente_hash',
        'token_agente_ultimo4', 'vinculado_en', 'ultimo_contacto_en', 'version_agente',
    ];

    protected function casts(): array
    {
        return [
            'impresion_automatica' => 'boolean', 'copias_ticket' => 'integer', 'activo' => 'boolean',
            'vinculado_en' => 'datetime', 'ultimo_contacto_en' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal'); }
    public function impresora(): BelongsTo { return $this->belongsTo(Impresora::class, 'id_impresora', 'id_impresora'); }
    public function turnos(): HasMany { return $this->hasMany(TurnoCaja::class, 'id_terminal', 'id_terminal'); }
}
