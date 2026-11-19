<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StoreMarcaRequest;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarcaController extends Controller
{
    public function index(): Response
    {
        $marcas = Marca::query()
            ->withCount('productos')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Marcas/Index', [
            'marcas' => $marcas,
        ]);
    }

    public function store(StoreMarcaRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $data['estado'] = $request->boolean('estado', true);

        $marca = Marca::create($data);

        if ($request->expectsJson()) {
            return response()->json(['data' => $marca], 201);
        }

        return to_route('marcas.index')->with('success', 'Marca registrada correctamente.');
    }

    public function update(StoreMarcaRequest $request, Marca $marca): RedirectResponse
    {
        $data = $request->validated();
        if ($request->has('estado')) {
            $data['estado'] = $request->boolean('estado');
        }

        $marca->update($data);

        return to_route('marcas.index')->with('success', 'Marca actualizada correctamente.');
    }

    /** Desactivación lógica para proteger el historial de artículos existentes */
    public function destroy(Marca $marca): RedirectResponse
    {
        $marca->update(['estado' => false]);

        return to_route('marcas.index')->with('success', 'Marca desactivada del catálogo.');
    }
}
