<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoVinculacionAgente extends Model
{
    protected $table = 'seguridad.codigos_vinculacion_agente';
    protected $primaryKey = 'id_codigo_vinculacion';

    protected $fillable = [
        'id_terminal', 'id_sucursal', 'id_usuario_creador', 'codigo_hash', 'expira_en', 'usado_en', 'identificador_agente',
    ];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime', 'usado_en' => 'datetime'];
    }

    public function terminal(): BelongsTo { return $this->belongsTo(TerminalPos::class, 'id_terminal', 'id_terminal'); }
}
