<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presentacion extends Model
{
    protected $table = 'catalogos.presentaciones';
    protected $primaryKey = 'id_presentacion';
    // La tabla de catálogos no define columnas created_at ni updated_at.
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function productoPresentaciones(): HasMany
    {
        return $this->hasMany(ProductoPresentacion::class, 'id_presentacion', 'id_presentacion');
    }
}
