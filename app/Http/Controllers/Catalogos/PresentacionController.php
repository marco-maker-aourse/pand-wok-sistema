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
        $nombre = $request->validated('nombre');

        // Alta rápida: el empaque pudo crearse en un intento anterior o estar desactivado;
        // se devuelve (y reactiva) el existente en lugar de fallar por nombre repetido.
        if ($request->esAltaRapida()) {
            $existente = Presentacion::query()->whereRaw('UPPER(nombre) = ?', [$nombre])->first();
            if ($existente) {
                if (! $existente->estado) {
                    $existente->update(['estado' => true]);
                }

                return response()->json(['data' => $existente->refresh()]);
            }
        }

        $presentacion = Presentacion::create([
            'nombre' => $nombre,
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
