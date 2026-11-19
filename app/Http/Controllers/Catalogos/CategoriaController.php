<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StoreCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    public function index(): Response
    {
        $categorias = Categoria::query()
            ->withCount('productos')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Categorias/Index', [
            'categorias' => $categorias,
        ]);
    }

    public function store(StoreCategoriaRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $data['estado'] = $request->boolean('estado', true);

        $categoria = Categoria::create($data);

        if ($request->expectsJson()) {
            return response()->json(['data' => $categoria], 201);
        }

        return to_route('categorias.index')->with('success', 'Categoría creada correctamente.');
    }

    public function update(StoreCategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $data = $request->validated();
        if ($request->has('estado')) {
            $data['estado'] = $request->boolean('estado');
        }

        $categoria->update($data);

        return to_route('categorias.index')->with('success', 'Categoría actualizada correctamente.');
    }

    /** Desactivación lógica para no romper productos históricos */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->update(['estado' => false]);

        return to_route('categorias.index')->with('success', 'Categoría desactivada del catálogo.');
    }
}
