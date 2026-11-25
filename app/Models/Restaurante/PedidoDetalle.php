<?php

namespace App\Models\Restaurante;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ítem del pedido con precio y nombre congelados al momento de tomarlo. */
class PedidoDetalle extends Model
{
    public const PENDIENTE = 'PENDIENTE';
    public const ENVIADO = 'ENVIADO';
    public const EN_PREPARACION = 'EN_PREPARACION';
    public const LISTO = 'LISTO';
    public const ENTREGADO = 'ENTREGADO';
    public const ANULADO = 'ANULADO';

    protected $table = 'restaurante.pedido_detalles';
    protected $primaryKey = 'id_detalle_pedido';
    protected $fillable = [
        'id_pedido', 'id_precio', 'id_producto', 'tipo_producto', 'nombre_producto', 'cantidad', 'precio_base',
        'precio_modificadores', 'precio_unitario', 'subtotal', 'observacion', 'estado',
        'cantidad_cobrada', 'id_usuario', 'id_comanda', 'enviado_en', 'motivo_anulacion',
        'id_usuario_anulacion', 'anulado_en', 'clave_idempotencia',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_base' => 'decimal:4',
            'precio_modificadores' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'cantidad_cobrada' => 'decimal:3',
            'enviado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function precio(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'id_precio', 'id_precio');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function modificadores(): HasMany
    {
        return $this->hasMany(PedidoDetalleModificador::class, 'id_detalle_pedido', 'id_detalle_pedido')->orderBy('id_detalle_modificador');
    }

    public function tipoRestaurante(): ?string
    {
        return $this->tipo_producto ?? $this->producto?->tipo_producto;
    }

    /** Una línea con cobros previos se sigue atendiendo por el flujo elegido al cobrarla. */
    public function scopePendientesDeEnvio(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE)->where('cantidad_cobrada', 0);
    }

    /** Pendiente = aún no salió a cocina; se puede editar o quitar si no tiene cobros. */
    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ANULADO;
    }

    public function tieneCobro(): bool
    {
        return (float) $this->cantidad_cobrada > 0;
    }
}
