<?php

namespace App\Models\Restaurante;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    use BelongsToSucursal;

    public const DISPONIBLE = 'DISPONIBLE';
    public const OCUPADA = 'OCUPADA';
    public const RESERVADA = 'RESERVADA';
    public const FUERA_SERVICIO = 'FUERA_SERVICIO';

    protected $table = 'restaurante.mesas';
    protected $primaryKey = 'id_mesa';
    protected $fillable = ['id_sucursal', 'id_area', 'codigo', 'capacidad', 'estado', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'capacidad' => 'integer', 'orden' => 'integer'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area', 'id_area');
    }

    public function pedidoAbierto(): HasOne
    {
        return $this->hasOne(Pedido::class, 'id_mesa', 'id_mesa')->where('estado', Pedido::ABIERTO);
    }

    /** Una mesa ocupada o fuera de servicio no puede recibir una cuenta nueva. */
    public function admiteNuevaCuenta(): bool
    {
        return $this->activo && in_array($this->estado, [self::DISPONIBLE, self::RESERVADA], true);
    }
}
