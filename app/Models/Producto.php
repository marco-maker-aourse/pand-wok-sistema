<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Restaurante\GrupoModificador;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'catalogos.productos';
    protected $primaryKey = 'id_producto';
    // El esquema aprobado no define created_at ni updated_at para este catálogo.
    public $timestamps = false;

    // Solo columnas reales definidas en catalogos.productos.
    protected $fillable = [
        'id_categoria',
        'id_marca',
        'id_unidad_base',
        'codigo_barras',
        'codigo_sunat',
        'nombre',
        'afecta_igv',
        'es_servicio',
        'usa_vencimiento',
        'estado',
        // Restaurante: PREPARADO (pasa por cocina), DIRECTO (venta tal cual) o INSUMO (solo inventario).
        'tipo_producto',
        // Número que el cliente dice al mozo y id del plato en la carta de origen (importación).
        'codigo_carta',
        'codigo_externo',
    ];

    public const PREPARADO = 'PREPARADO';
    public const DIRECTO = 'DIRECTO';
    public const INSUMO = 'INSUMO';
    public const TIPOS = [self::PREPARADO, self::DIRECTO, self::INSUMO];

    protected function casts(): array
    {
        return [
            'afecta_igv'      => 'boolean',
            'es_servicio'     => 'boolean',
            'usa_vencimiento' => 'boolean',
            'estado'          => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id_marca');
    }

    public function unidadBase(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_base', 'id_unidad');
    }

    public function presentaciones(): HasMany
    {
        return $this->hasMany(ProductoPresentacion::class, 'id_producto', 'id_producto');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'id_producto', 'id_producto');
    }

    public function kardex(): HasMany
    {
        return $this->hasMany(Kardex::class, 'id_producto', 'id_producto');
    }

    /** Grupos de modificadores que el mozo puede elegir al pedir este plato. */
    public function gruposModificadores(): BelongsToMany
    {
        return $this->belongsToMany(GrupoModificador::class, 'restaurante.producto_grupo_modificador', 'id_producto', 'id_grupo')
            ->withPivot('orden')
            ->orderByPivot('orden');
    }
}
