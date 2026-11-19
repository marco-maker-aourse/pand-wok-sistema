<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\GuardarEstacionRequest;
use App\Models\Categoria;
use App\Models\Impresora;
use App\Models\Restaurante\Estacion;
use App\Models\TerminalPos;
use App\Services\Restaurante\GestionarEstacionesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstacionController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(private readonly GestionarEstacionesService $estaciones) {}

    public function index(Request $request): Response
    {
        $sucursal = $request->user()->idSucursalActiva();

        return Inertia::render('Restaurante/Estaciones', [
            'estaciones' => $this->estaciones->listado($sucursal),
            'categorias' => Categoria::query()->orderBy('nombre')->get(['id_categoria', 'nombre']),
            'impresoras' => Impresora::query()->where('id_sucursal', $sucursal)->orderBy('nombre')->get(['id_impresora', 'nombre', 'cloudprnt_clave_hash'])
                ->map(fn (Impresora $impresora): array => [
                    ...$impresora->only(['id_impresora', 'nombre']),
                    'cloudprnt' => (bool) $impresora->cloudprnt_clave_hash,
                ]),
            'terminales' => TerminalPos::query()->where('id_sucursal', $sucursal)->orderBy('nombre')->get(['id_terminal', 'nombre']),
            'puedeVerCocina' => $request->user()->hasPermission('cocina.ver'),
        ]);
    }

    public function guardar(GuardarEstacionRequest $request, ?int $estacion = null): RedirectResponse
    {
        $sucursal = $request->user()->idSucursalActiva();
        if ($estacion !== null) {
            Estacion::query()->where('id_sucursal', $sucursal)->findOrFail($estacion);
        }

        $datos = $request->validated();
        $this->enDominio(fn () => DB::transaction(function () use ($datos, $sucursal, $estacion): void {
            $registro = $this->estaciones->guardar($datos, $sucursal, $estacion);
            $this->estaciones->asignarCategorias($registro->id_estacion, $datos['categorias'], $sucursal);
        }, 3));

        return back()->with('success', 'Estación guardada.');
    }
}
