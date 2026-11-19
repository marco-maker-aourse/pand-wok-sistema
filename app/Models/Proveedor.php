<?php

namespace App\Models;

use App\Models\Ingreso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'catalogos.proveedores';
    protected $primaryKey = 'id_proveedor';
    public $timestamps = false; // Verificado en Tinker: la tabla no tiene created_at ni updated_at

    protected $fillable = [
        'id_tipo_documento',
        'id_ubigeo',
        'num_documento',
        'razon_social',
        'direccion_fiscal',
        'telefono',
        'email',
        'estado',
    ];

    /** Alias de lectura para las pantallas existentes; la columna física es num_documento. */
    protected $appends = ['ruc'];

    public static bool $permitirCambioDocumento = false;

    public function tieneIngresos(): bool
    {
        return Ingreso::withoutGlobalScope(\App\Models\Scopes\SucursalScope::class)
            ->where('id_proveedor', $this->id_proveedor)
            ->exists();
    }

    public function getRucAttribute(): ?string
    {
        return $this->num_documento;
    }

    /** Coincidencia parcial por razón social o RUC. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        return $query->when($termino !== '', fn (Builder $filtro) => $filtro->where(fn (Builder $q) => $q
            ->where('razon_social', 'ILIKE', '%'.addcslashes($termino, '%_\\').'%')
            ->orWhere('num_documento', 'ILIKE', '%'.addcslashes($termino, '%_\\').'%')));
    }

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    /**
     * Blindar num_documento (RUC): no permitir modificarlo una vez creado el proveedor, salvo que se permita explícitamente.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (Proveedor $proveedor) {
            if ($proveedor->isDirty('num_documento') && ! static::$permitirCambioDocumento) {
                throw new \Illuminate\Database\Eloquent\MassAssignmentException(
                    'El RUC del proveedor no se puede modificar una vez registrado.'
                );
            }
        });
    }
}
