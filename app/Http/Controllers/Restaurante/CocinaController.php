<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\EnviarComandaRequest;
use App\Http\Requests\Restaurante\EstadoComandaRequest;
use App\Models\Restaurante\Comanda;
use App\Services\Restaurante\CocinaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CocinaController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(private readonly CocinaService $cocina) {}

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $sucursal = $usuario->idSucursalActiva();
        $filtro = $request->validate(['estacion' => ['nullable', 'integer', 'min:0']])['estacion'] ?? null;
        $estacion = $filtro === null ? null : (int) $filtro;

        return Inertia::render('Restaurante/Cocina', [
            'comandas' => fn () => $this->cocina->tablero($sucursal, $estacion),
            'estaciones' => fn () => $this->cocina->estaciones($sucursal),
            'estacion' => $estacion,
            'idSucursal' => $sucursal,
            'puedeGestionar' => $usuario->hasPermission('cocina.gestionar'),
            'puedeConfigurar' => $usuario->hasPermission('cocina.configurar'),
        ]);
    }

    public function estado(EstadoComandaRequest $request, int $comanda): RedirectResponse
    {
        $sucursal = $request->user()->idSucursalActiva();
        Comanda::query()->where('id_sucursal', $sucursal)->findOrFail($comanda);
        $this->enDominio(fn () => $this->cocina->cambiarEstado($comanda, $request->validated('estado'), $sucursal));

        return back();
    }

    public function reimprimir(EnviarComandaRequest $request, int $comanda): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario->hasPermission('cocina.gestionar') || $usuario->hasPermission('comandas.enviar'), 403);
        $sucursal = $usuario->idSucursalActiva();
        Comanda::query()->where('id_sucursal', $sucursal)->findOrFail($comanda);
        $trabajo = $this->enDominio(fn () => $this->cocina->reimprimir($comanda, $request->validated('clave_envio'), $sucursal));

        return $trabajo
            ? back()->with('success', 'Reimpresión en cola.')
            : throw \Illuminate\Validation\ValidationException::withMessages(['general' => 'No hay una impresora activa o ítems vigentes para reimprimir.']);
    }
}
