<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Services\Sucursales\ContextoSucursalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'seguridad.usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'nombres',
        'id_rol',
        'id_sucursal',
        'id_tipo_documento',
        'id_ubigeo',
        'num_documento',
        'email',
        'password',
        'pin_cajero',
        'estado',
    ];

    protected $hidden = [
        'password',
        'pin_cajero',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin_cajero' => 'hashed',
            'estado' => 'boolean',
        ];
    }
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }

    /** Sucursales adicionales asignadas al usuario (id_sucursal es su sede principal). */
    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'seguridad.usuario_sucursales', 'id_usuario', 'id_sucursal');
    }

    /**
     * Sucursal de trabajo resuelta para esta petición (memoizada). No se persiste:
     * la decide ContextoSucursalService a partir de la sesión, validando el acceso.
     */
    private ?int $sucursalActivaSesion = null;

    public function usarSucursalActiva(int $branchId): void
    {
        $this->sucursalActivaSesion = $branchId;
    }

    /**
     * Sucursales activas en las que el usuario puede operar: todas para un
     * administrador; su sede principal y las asignadas para el resto.
     */
    public function sucursalesDisponibles(): Collection
    {
        return Sucursal::query()
            ->where('estado', true)
            ->when(! $this->isAdministrator(), fn ($query) => $query->where(fn ($acceso) => $acceso
                ->whereKey($this->id_sucursal)
                ->orWhereIn('id_sucursal', DB::table('seguridad.usuario_sucursales')->select('id_sucursal')->where('id_usuario', $this->id_usuario))))
            ->orderBy('nombre')
            ->get(['id_sucursal', 'nombre', 'codigo_establecimiento']);
    }

    /**
     * Verifica si el usuario es Administrador utilizando la relación en memoria si está cargada.
     */
    public function isAdministrator(): bool
    {
        $rolesAdministrativos = [
            'Administrador General',
            'Administrador',
        ];

        if ($this->relationLoaded('rol')) {
            return $this->rol
                && $this->rol->estado
                && in_array($this->rol->nombre, $rolesAdministrativos, true);
        }

        return $this->rol()
            ->whereIn('nombre', $rolesAdministrativos)
            ->where('estado', true)
            ->exists();
    }
    /**
     * El rol se carga una sola vez por petición y se reutiliza en Inertia e
     * isAdministrator(); solo el permiso concreto requiere una consulta.
     */
    public function hasPermission(string $permission): bool
    {
        $rol = $this->rol;

        if (! $rol || ! $rol->estado) {
            return false;
        }

        if ($rol->nombre === 'Administrador General') {
            return true;
        }

        return $rol->permisos()
            ->where('codigo', $permission)
            ->where('estado', true)
            ->exists();
    }

    /**
     * Sucursal sobre la que opera la sesión. Es la única fuente del contexto de
     * sucursal: el backend nunca la toma de datos enviados por el navegador.
     */
    public function idSucursalActiva(): int
    {
        $this->sucursalActivaSesion ??= app(ContextoSucursalService::class)->resolver($this);

        abort_unless($this->sucursalActivaSesion, 422, 'El usuario no tiene una sucursal asignada.');

        return $this->sucursalActivaSesion;
    }

    /**
     * Verifica acceso a una sucursal específica.
     */
    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        if ($this->relationLoaded('sucursales')) {
            return $this->sucursales->contains('id_sucursal', $branchId);
        }

        return $this->sucursales()->whereKey($branchId)->exists();
    }
}
