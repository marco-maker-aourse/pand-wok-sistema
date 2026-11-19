<?php

namespace App\Models;

use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $table = 'catalogos.clientes';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false; // Verificado en Tinker: la tabla no tiene created_at ni updated_at

    protected $fillable = [
        'id_tipo_documento',
        'id_ubigeo',
        'num_documento',
        'nombre_razon_social',
        'direccion_fiscal',
        'telefono',
        'email',
        'estado',
    ];

    public static bool $permitirCambioDocumento = false;

    public function tieneVentas(): bool
    {
        return Venta::withoutGlobalScope(\App\Models\Scopes\SucursalScope::class)
            ->where('id_cliente', $this->id_cliente)
            ->exists();
    }

    /**
     * Blindar num_documento: no permitir modificarlo una vez creado el cliente, salvo que se permita explícitamente.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (Cliente $cliente) {
            if ($cliente->isDirty('num_documento') && ! static::$permitirCambioDocumento) {
                throw new \Illuminate\Database\Eloquent\MassAssignmentException(
                    'El número de documento no se puede modificar una vez registrado.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'id_tipo_documento', 'id_tipo_documento');
    }

    /** Coincidencia parcial por nombre/razón social o número de documento. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        return $query->when($termino !== '', fn (Builder $filtro) => $filtro->where(fn (Builder $q) => $q
            ->where('nombre_razon_social', 'ILIKE', '%'.addcslashes($termino, '%_\\').'%')
            ->orWhere('num_documento', 'ILIKE', '%'.addcslashes($termino, '%_\\').'%')));
    }
}
