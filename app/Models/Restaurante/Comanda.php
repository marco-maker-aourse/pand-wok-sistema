<?php

namespace App\Models\Restaurante;

use App\Models\Concerns\BelongsToSucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Envío de ítems de un pedido a una estación de cocina. */
class Comanda extends Model
{
    use BelongsToSucursal;

    public const PENDIENTE = 'PENDIENTE';
    public const EN_PREPARACION = 'EN_PREPARACION';
    public const LISTA = 'LISTA';
    public const ENTREGADA = 'ENTREGADA';
    public const ANULADA = 'ANULADA';
    public const ACTIVAS = [self::PENDIENTE, self::EN_PREPARACION, self::LISTA];

    protected $table = 'restaurante.comandas';
    protected $primaryKey = 'id_comanda';
    protected $fillable = [
        'id_sucursal', 'id_pedido', 'id_estacion', 'numero', 'estado', 'clave_envio', 'id_usuario',
        'enviada_en', 'iniciada_en', 'lista_en', 'entregada_en',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'enviada_en' => 'datetime',
            'iniciada_en' => 'datetime',
            'lista_en' => 'datetime',
            'entregada_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(Estacion::class, 'id_estacion', 'id_estacion');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'id_comanda', 'id_comanda');
    }
}
