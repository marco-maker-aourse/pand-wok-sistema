<?php

namespace App\Models\Restaurante;

use App\Models\Cliente;
use App\Models\Concerns\BelongsToSucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cuenta de una mesa, para llevar o delivery. No es una venta: se cobra en la Etapa 6. */
class Pedido extends Model
{
    use BelongsToSucursal;

    public const MESA = 'MESA';
    public const PARA_LLEVAR = 'PARA_LLEVAR';
    public const DELIVERY = 'DELIVERY';
    public const TIPOS = [self::MESA, self::PARA_LLEVAR, self::DELIVERY];

    public const ABIERTO = 'ABIERTO';
    public const CERRADO = 'CERRADO';
    public const ANULADO = 'ANULADO';
    public const UNIDO = 'UNIDO';

    protected $table = 'restaurante.pedidos';
    protected $primaryKey = 'id_pedido';
    protected $fillable = [
        'id_sucursal', 'numero', 'tipo', 'id_mesa', 'id_usuario', 'id_cliente', 'comensales',
        'nombre_contacto', 'telefono_contacto', 'direccion_entrega', 'observacion', 'estado',
        'clave_idempotencia', 'abierto_en', 'cerrado_en', 'id_usuario_cierre', 'motivo_anulacion',
        'id_pedido_destino',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'comensales' => 'integer',
            'abierto_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'id_mesa', 'id_mesa');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'id_pedido', 'id_pedido');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PedidoEvento::class, 'id_pedido', 'id_pedido');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ABIERTO;
    }
}
