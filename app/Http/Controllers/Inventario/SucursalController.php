<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\StoreSucursalRequest;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de sucursales. Cada sucursal configura después sus propios Datos
 * Generales (RUC, series, pie de ticket) al trabajar en ella.
 */
class SucursalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return Inertia::render('Sucursales/Index', [
            'sucursales' => Sucursal::query()
                ->withCount(['usuarios' => fn ($query) => $query->where('estado', true)])
                ->orderBy('nombre')
                ->get(['id_sucursal', 'nombre', 'direccion', 'telefono', 'responsable', 'codigo_establecimiento', 'slug', 'horario_atencion', 'estado']),
            'sucursalActivaId' => $request->user()->idSucursalActiva(),
        ]);
    }

    public function store(StoreSucursalRequest $request): RedirectResponse
    {
        Sucursal::create([...$request->validated(), 'estado' => $request->boolean('estado', true)]);

        return to_route('sucursales.index')->with('success', 'Sucursal registrada. Configura sus datos generales al trabajar en ella.');
    }

    public function update(StoreSucursalRequest $request, Sucursal $sucursal): RedirectResponse
    {
        $data = $request->validated();

        if ($request->has('estado')) {
            $data['estado'] = $request->boolean('estado');
            if (! $data['estado']) {
                $this->verificarDesactivable($request, $sucursal);
            }
        }

        $sucursal->update($data);

        return to_route('sucursales.index')->with('success', 'Sucursal actualizada correctamente.');
    }

    /** Desactivación lógica: ventas, Kardex y comprobantes históricos se conservan. */
    public function destroy(Request $request, Sucursal $sucursal): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $this->verificarDesactivable($request, $sucursal);

        $sucursal->update(['estado' => false]);

        return to_route('sucursales.index')->with('success', 'Sucursal desactivada.');
    }

    private function verificarDesactivable(Request $request, Sucursal $sucursal): void
    {
        if ((int) $sucursal->id_sucursal === $request->user()->idSucursalActiva()) {
            throw ValidationException::withMessages(['estado' => 'No puedes desactivar la sucursal en la que estás trabajando.']);
        }

        if ($sucursal->usuarios()->where('estado', true)->exists()) {
            throw ValidationException::withMessages(['estado' => 'La sucursal es la sede principal de usuarios activos. Reasígnalos antes de desactivarla.']);
        }
    }
}
