<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'seguridad.permisos';
    protected $primaryKey = 'id_permiso';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre', 'modulo', 'descripcion', 'estado'];
    protected function casts(): array { return ['estado' => 'boolean']; }
    public function roles(): BelongsToMany { return $this->belongsToMany(Rol::class, 'seguridad.rol_permisos', 'id_permiso', 'id_rol'); }
}
