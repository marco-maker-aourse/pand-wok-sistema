<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;

use App\Http\Requests\Seguridad\StoreRolRequest;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Usuarios/Roles', [
            'roles' => Rol::query()
                ->with('permisos:id_permiso,codigo')
                ->orderBy('nombre')
                ->paginate(15)
                ->through(fn (Rol $role) => [
                    'id_rol'      => $role->id_rol,
                    'nombre'      => $role->nombre,
                    'descripcion' => $role->descripcion,
                    'estado'      => $role->estado,
                    'permisos'    => $role->permisos->pluck('id_permiso'),
                ]),
            'permisos' => Permiso::query()
                ->where('estado', true)
                ->orderBy('modulo')
                ->orderBy('nombre')
                ->get(['id_permiso', 'nombre', 'codigo', 'modulo']),
        ]);
    }

    public function store(StoreRolRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            // Usamos safe()->except('permisos') para no intentar insertar 'permisos' en la tabla roles
            $role = Rol::create($request->safe()->except('permisos'));
            $role->permisos()->sync($request->input('permisos', []));
        });

        return to_route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    public function update(StoreRolRequest $request, Rol $rol): RedirectResponse
    {
        DB::transaction(function () use ($request, $rol) {
            $rol->update($request->safe()->except('permisos'));
            $rol->permisos()->sync($request->input('permisos', []));
        });

        return to_route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Rol $rol): RedirectResponse
    {
        abort_if($rol->usuarios()->exists(), 422, 'No puedes eliminar un rol asignado a usuarios.');
        
        $rol->update(['estado' => false]);

        return to_route('roles.index')->with('success', 'Rol desactivado correctamente.');
    }
}
