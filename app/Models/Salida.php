<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salida extends Model
{
    use BelongsToSucursal;
    protected $table = 'operaciones.salidas';
    protected $primaryKey = 'id_salida';
    public $timestamps = false;

    protected $fillable = [
        'id_sucursal',
        'id_usuario',
        'motivo',
        'observacion',
        'fecha_salida',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_salida' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSalida::class, 'id_salida', 'id_salida');
    }
}
