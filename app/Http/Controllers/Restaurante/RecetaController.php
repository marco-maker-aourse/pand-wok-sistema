<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\GuardarRecetaRequest;
use App\Http\Requests\Restaurante\RegistrarMermaRequest;
use App\Services\Restaurante\RecetasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecetaController extends Controller
{
    use ConvierteErroresDeDominio;

    public function index(Request $request, RecetasService $servicio): Response
    {
        $usuario = $request->user();
        $verRecetas = $request->routeIs('restaurante.recetas');

        return Inertia::render('Restaurante/Recetas', [
            ...$servicio->catalogo($usuario->idSucursalActiva(), $verRecetas),
            'verRecetas' => $verRecetas,
            'puedeRecetas' => $usuario->hasPermission('recetas.gestionar'),
            'puedeMermas' => $usuario->hasPermission('mermas.registrar'),
        ]);
    }

    public function guardar(GuardarRecetaRequest $request, int $precio, RecetasService $servicio): RedirectResponse
    {
        $this->enDominio(fn () => $servicio->guardar($precio, $request->user()->idSucursalActiva(), $request->validated('ingredientes')));

        return back()->with('success', 'Receta guardada.');
    }

    public function merma(RegistrarMermaRequest $request, RecetasService $servicio): RedirectResponse
    {
        $usuario = $request->user();
        $this->enDominio(fn () => $servicio->registrarMerma($usuario->idSucursalActiva(), $usuario->id_usuario, $request->validated()));

        return back()->with('success', 'Merma registrada.');
    }
}
