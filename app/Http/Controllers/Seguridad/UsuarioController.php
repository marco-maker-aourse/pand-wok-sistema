<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;

use App\Http\Requests\Seguridad\StoreUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function store(StoreUsuarioRequest $request): RedirectResponse|JsonResponse
    {
        $data = collect($request->validated())->except('sucursales_adicionales')->all();
        $this->verificarSucursalAdministrable($request, (int) $data['id_sucursal']);

        $usuario = DB::transaction(function () use ($data, $request) {
            // El casting 'hashed' en User.php encripta la contraseña automáticamente
            $usuario = User::create($data);
            // La sede y las sucursales de acceso nacen sincronizadas.
            $this->sincronizarSucursales($usuario, $request->sucursalesAdicionales() ?? []);

            return $usuario;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente.',
                'data' => $usuario,
            ], 201);
        }

        return to_route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(StoreUsuarioRequest $request, User $usuario): RedirectResponse|JsonResponse
    {
        $data = collect($request->validated())->except('sucursales_adicionales')->all();
        $this->verificarSucursalAdministrable($request, (int) $usuario->id_sucursal);
        $this->verificarSucursalAdministrable($request, (int) $data['id_sucursal']);

        DB::transaction(function () use ($data, $usuario, $request) {
            // Si la contraseña no se modifica en el formulario, se remueve del array
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            if (blank($data['pin_cajero'] ?? null)) {
                unset($data['pin_cajero']);
            }

            $sedeAnterior = (int) $usuario->id_sucursal;
            $usuario->update($data);

            // Si quien edita no gestiona sucursales (no es administrador), se
            // conservan las asignaciones existentes; solo se actualiza la sede.
            $adicionales = $request->sucursalesAdicionales()
                ?? $usuario->sucursales()->pluck('inventario.sucursales.id_sucursal')
                    ->map(fn ($id): int => (int) $id)
                    ->reject(fn (int $id): bool => $id === $sedeAnterior)
                    ->all();
            $this->sincronizarSucursales($usuario, $adicionales);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.',
                'data' => $usuario->fresh(),
            ]);
        }

        return to_route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse|JsonResponse
    {
        // Impide que un usuario se desactive a sí mismo
        abort_if($request->user()->is($usuario), 422, 'No puedes desactivar tu propia sesión.');
        $this->verificarSucursalAdministrable($request, (int) $usuario->id_sucursal);

        $usuario->update(['estado' => false]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario desactivado correctamente.',
            ]);
        }

        return to_route('usuarios.index')->with('success', 'Usuario desactivado; su historial se conserva.');
    }

    public function updatePermissions(Request $request, Rol $rol): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['integer', 'exists:seguridad.permisos,id_permiso'],
        ]);

        DB::transaction(function () use ($rol, $data) {
            $rol->permisos()->sync($data['permisos']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Permisos del rol actualizados correctamente.',
            ]);
        }

        return back()->with('success', 'Permisos del rol actualizados correctamente.');
    }

    /** Un administrador de sede no puede gestionar cuentas ajenas a su sucursal. */
    private function verificarSucursalAdministrable(Request $request, int $idSucursal): void
    {
        $actor = $request->user();
        abort_unless($actor->isAdministrator() || (int) $actor->idSucursalActiva() === $idSucursal, 403, 'No puedes administrar usuarios de otra sucursal.');
    }

    /** La sede siempre figura entre las sucursales de acceso, junto con las adicionales. */
    private function sincronizarSucursales(User $usuario, array $adicionales): void
    {
        $usuario->sucursales()->sync(
            collect([(int) $usuario->id_sucursal, ...$adicionales])->unique()->values()->all()
        );
    }
}
