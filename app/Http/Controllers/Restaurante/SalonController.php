<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\CambiarEstadoMesaRequest;
use App\Http\Requests\Restaurante\GuardarAreaRequest;
use App\Http\Requests\Restaurante\GuardarMesaRequest;
use App\Services\Restaurante\GestionarSalonService;
use App\Services\Restaurante\PresentarRestauranteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Plano del salón y configuración de áreas y mesas de la sucursal activa. */
class SalonController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(
        private readonly GestionarSalonService $salon,
        private readonly PresentarRestauranteService $presentar,
    ) {}

    public function index(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Restaurante/Salon', [
            ...$this->presentar->salon($usuario->idSucursalActiva()),
            'puede' => [
                'abrir' => $usuario->hasPermission('pedidos.registrar'),
                'configurar' => $usuario->hasPermission('mesas.gestionar'),
            ],
        ]);
    }

    public function configuracion(Request $request): Response
    {
        return Inertia::render('Restaurante/ConfiguracionSalon', [
            'areas' => $this->presentar->configuracionSalon($request->user()->idSucursalActiva()),
        ]);
    }

    public function guardarArea(GuardarAreaRequest $request, ?int $area = null): RedirectResponse
    {
        $this->enDominio(fn () => $this->salon->guardarArea($request->validated(), $request->user()->idSucursalActiva(), $area), 'nombre');

        return back()->with('success', $area ? 'Área actualizada.' : 'Área creada.');
    }

    public function guardarMesa(GuardarMesaRequest $request, ?int $mesa = null): RedirectResponse
    {
        $this->enDominio(fn () => $this->salon->guardarMesa($request->validated(), $request->user()->idSucursalActiva(), $mesa), 'codigo');

        return back()->with('success', $mesa ? 'Mesa actualizada.' : 'Mesa creada.');
    }

    public function cambiarEstadoMesa(CambiarEstadoMesaRequest $request, int $mesa): RedirectResponse
    {
        $actualizada = $this->enDominio(fn () => $this->salon->cambiarEstadoMesa($mesa, $request->validated('estado'), $request->user()->idSucursalActiva()), 'estado');

        return back()->with('success', "Mesa {$actualizada->codigo} actualizada.");
    }
}
