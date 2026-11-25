<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $buscar = $request->string('buscar')->trim()->value();
        // LOWER + LIKE funciona igual en PostgreSQL y en la base de pruebas.
        $patron = '%'.mb_strtolower(addcslashes($buscar, '%_\\')).'%';

        $users = User::query()
            ->select(['id_usuario', 'id_rol', 'id_sucursal', 'id_tipo_documento', 'nombres', 'email', 'num_documento', 'pin_cajero', 'estado'])
            ->with(['rol:id_rol,nombre', 'sucursal:id_sucursal,nombre', 'sucursales:id_sucursal,nombre'])
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->where('id_sucursal', $request->user()->idSucursalActiva()))
            ->when($buscar !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereRaw('LOWER(nombres) LIKE ?', [$patron])
                ->orWhereRaw('LOWER(email) LIKE ?', [$patron])
                ->orWhereRaw('LOWER(num_documento) LIKE ?', [$patron])))
            ->orderBy('nombres')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id_usuario' => $user->id_usuario,
                'nombres' => $user->nombres,
                'email' => $user->email,
                'num_documento' => $user->num_documento,
                'estado' => $user->estado,
                'rol' => $user->rol?->nombre,
                'id_rol' => $user->id_rol,
                'sucursal' => $user->sucursal?->nombre,
                'id_sucursal' => $user->id_sucursal,
                // Acceso a otras sucursales además de la sede (para cambiar de sucursal de trabajo).
                'sucursales_adicionales' => $user->sucursales
                    ->reject(fn (Sucursal $sucursal): bool => (int) $sucursal->id_sucursal === (int) $user->id_sucursal)
                    ->map(fn (Sucursal $sucursal): array => ['id_sucursal' => $sucursal->id_sucursal, 'nombre' => $sucursal->nombre])
                    ->values(),
                'id_tipo_documento' => $user->id_tipo_documento,
                // El frontend solo necesita conocer si la credencial existe;
                // nunca recibe el PIN ni el hash almacenado.
                'tiene_pin_cajero' => filled($user->getRawOriginal('pin_cajero')),
            ]);

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $users,
            'filters' => ['buscar' => $buscar],
            'roles' => Rol::query()
                ->where('estado', true)
                ->with(['permisos:id_permiso,codigo'])
                ->orderBy('nombre')
                ->get(['id_rol', 'nombre', 'descripcion'])
                ->map(fn (Rol $role) => [
                    'id_rol' => $role->id_rol,
                    'nombre' => $role->nombre,
                    'descripcion' => $role->descripcion,
                    'permisos' => $role->permisos->pluck('id_permiso')->values(),
                ]),
            'permisos' => Permiso::query()
                ->where('estado', true)
                ->orderBy('modulo')
                ->orderBy('nombre')
                ->get(['id_permiso', 'codigo', 'nombre', 'modulo']),
            'sucursales' => Sucursal::query()
                ->where('estado', true)
                ->when(! $request->user()->isAdministrator(), fn ($query) => $query->whereKey($request->user()->idSucursalActiva()))
                ->orderBy('nombre')
                ->get(['id_sucursal', 'nombre']),
            'tiposDocumento' => TipoDocumento::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id_tipo_documento', 'codigo_sunat', 'nombre', 'longitud_exacta']),
            'puedeGestionarPin' => $request->user()->isAdministrator(),
            'puedeAsignarSucursales' => $request->user()->isAdministrator(),
        ]);
    }
}
