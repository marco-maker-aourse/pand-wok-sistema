<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StorePresentacionRequest;
use App\Models\Presentacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PresentacionController extends Controller
{
    public function index(): Response
    {
        $presentations = Presentacion::query()
            ->withCount('productoPresentaciones')
            ->orderBy('nombre')
            ->get();

        // Apunta al archivo que trajiste del viernes y envía ambas variables por compatibilidad
        return Inertia::render('Presentaciones/Index', [
            'presentaciones' => $presentations,
            'presentations'  => $presentations,
        ]);
    }

    public function store(StorePresentacionRequest $request): RedirectResponse|JsonResponse
    {
        $presentacion = Presentacion::create([
            'nombre' => trim($request->validated('nombre')),
            'estado' => $request->boolean('estado', true),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $presentacion], 201);
        }

        return to_route('presentaciones.index')->with('success', 'Presentación guardada correctamente.');
    }

    public function update(StorePresentacionRequest $request, Presentacion $presentacion): RedirectResponse
    {
        $presentacion->update([
            'nombre' => trim($request->validated('nombre')),
            'estado' => $request->boolean('estado', true),
        ]);

        return to_route('presentaciones.index')->with('success', 'Presentación actualizada correctamente.');
    }

    public function destroy(Presentacion $presentacion): RedirectResponse
    {
        if ($presentacion->productoPresentaciones()->exists()) {
            return back()->withErrors([
                'error' => 'No puedes eliminar este empaque porque ya está asignado a productos existentes.',
            ]);
        }

        $presentacion->delete();

        return to_route('presentaciones.index')->with('success', 'Presentación eliminada.');
    }
}
