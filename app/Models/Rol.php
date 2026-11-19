<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Rol extends Model
{
    protected $table = 'seguridad.roles';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    protected function casts(): array { return ['estado' => 'boolean']; }

    public function permisos(): BelongsToMany { return $this->belongsToMany(Permiso::class, 'seguridad.rol_permisos', 'id_rol', 'id_permiso'); }
    public function usuarios(): HasMany { return $this->hasMany(User::class, 'id_rol', 'id_rol'); }
}
