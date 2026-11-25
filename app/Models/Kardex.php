<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Kardex extends Model
{
    use BelongsToSucursal;

    protected $table = 'inventario.kardex';
    protected $primaryKey = 'id_kardex';

    public $timestamps = false;

    /** Columnas de origen (migración 2026_11_19_000100); se consultan una vez por proceso. */
    private static ?bool $registraOrigen = null;

    protected $fillable = [
        'id_producto',
        'id_sucursal',
        'cantidad_entrada',
        'cantidad_salida',
        'stock_actual',
        'precio_costo',
        'ultima_actualizacion',
        'tipo_operacion',
        'id_referencia',
        'documento',
        'id_usuario',
    ];

    protected $casts = [
        'cantidad_entrada'     => 'decimal:4',
        'cantidad_salida'      => 'decimal:4',
        'stock_actual'         => 'decimal:4',
        'precio_costo'         => 'decimal:4',
        'ultima_actualizacion' => 'datetime',
    ];

    /**
     * Si la base ya tiene las columnas de origen. El código se despliega antes de que la
     * migración se aplique manualmente en producción: mientras tanto no se escriben ni leen.
     */
    public static function registraOrigen(): bool
    {
        return self::$registraOrigen ??= Schema::hasColumn('inventario.kardex', 'tipo_operacion');
    }

    /** Solo para pruebas: vuelve a consultar el esquema. */
    public static function olvidarEsquema(): void
    {
        self::$registraOrigen = null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }
}