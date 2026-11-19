<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Services\Sucursales\ContextoSucursalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Cambio de la sucursal de trabajo de la sesión (no modifica la sede del usuario). */
class SucursalActivaController extends Controller
{
    public function update(Request $request, ContextoSucursalService $contexto): RedirectResponse
    {
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer'],
        ]);
        $sucursal = $contexto->cambiar($request->user(), (int) $data['id_sucursal']);

        return back()->with('success', "Ahora trabajas en la sucursal {$sucursal->nombre}.");
    }
}
