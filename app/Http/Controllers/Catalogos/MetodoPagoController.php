<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Http\Requests\Catalogos\StoreMetodoPagoRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetodoPagoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MetodosPago/Index', [
            'metodos_pago' => MetodoPago::query()
                ->orderBy('id_metodo')
                ->get(),
        ]);
    }

    public function store(StoreMetodoPagoRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MetodoPago::create([
            'nombre'              => $validated['nombre'],
            'tipo'                => strtoupper($validated['tipo']),
            'comision_porcentaje' => $validated['comision_porcentaje'] ?? 0,
            'comision_fija'       => $validated['comision_fija'] ?? 0,
            'requiere_referencia' => $validated['requiere_referencia'] ?? false,
        ]);

        return back()->with('success', 'Método de pago registrado exitosamente.');
    }

    public function update(StoreMetodoPagoRequest $request, MetodoPago $metodoPago): RedirectResponse
    {
        $validated = $request->validated();

        $metodoPago->update([
            'nombre'              => $validated['nombre'],
            'tipo'                => strtoupper($validated['tipo']),
            'comision_porcentaje' => $validated['comision_porcentaje'] ?? 0,
            'comision_fija'       => $validated['comision_fija'] ?? 0,
            'requiere_referencia' => $validated['requiere_referencia'] ?? false,
        ]);

        return back()->with('success', 'Método de pago actualizado exitosamente.');
    }

    public function destroy(MetodoPago $metodoPago): RedirectResponse
    {
        try {
            $metodoPago->delete();
            return back()->with('success', 'Método de pago eliminado exitosamente.');
        } catch (QueryException $e) {
            return back()->withErrors([
                'error' => 'No se puede eliminar este método de pago porque ya fue utilizado en ventas anteriores o cierres de caja.'
            ]);
        }
    }
}
