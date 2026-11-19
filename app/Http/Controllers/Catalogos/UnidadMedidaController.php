<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StoreUnidadMedidaRequest;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnidadMedidaController extends Controller
{
    public function index(): Response
    {
        $unidades = UnidadMedida::query()
            ->withCount('productos')
            ->orderBy('nombre')
            ->get();

        // Apunta directamente a resources/js/Pages/Unidades/Index.jsx
        return Inertia::render('Unidades/Index', [
            'unidades' => $unidades,
            'units'    => $unidades, // Compatibilidad
        ]);
    }

    public function store(StoreUnidadMedidaRequest $request): RedirectResponse|JsonResponse
    {
        $unidad = UnidadMedida::create([
            'nombre'       => trim($request->validated('nombre')),
            'codigo_sunat' => $request->filled('codigo_sunat') ? strtoupper(trim($request->validated('codigo_sunat'))) : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $unidad], 201);
        }

        return to_route('unidades.index')->with('success', 'Unidad de medida guardada correctamente.');
    }

    public function update(StoreUnidadMedidaRequest $request, UnidadMedida $unidad): RedirectResponse
    {
        $unidad->update([
            'nombre'       => trim($request->validated('nombre')),
            'codigo_sunat' => $request->filled('codigo_sunat') ? strtoupper(trim($request->validated('codigo_sunat'))) : null,
        ]);

        return to_route('unidades.index')->with('success', 'Unidad de medida actualizada.');
    }

    public function destroy(UnidadMedida $unidad): RedirectResponse
    {
        if ($unidad->productos()->exists()) {
            return back()->withErrors([
                'error' => 'No puedes eliminar esta unidad porque ya está asignada a productos en el inventario.',
            ]);
        }

        $unidad->delete();

        return to_route('unidades.index')->with('success', 'Unidad de medida eliminada.');
    }
}
