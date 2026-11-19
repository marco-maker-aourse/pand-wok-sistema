<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;

use App\Http\Requests\Operaciones\StoreSalidaRequest;
use App\Models\Producto;
use App\Models\Salida;
use App\Services\Salidas\RegistrarSalidaService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalidaController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        $salidas = Salida::query()
            ->with([
                'usuario:id_usuario,nombres',
                'detalles.precio.producto:id_producto,nombre,codigo_barras',
                'detalles.precio.presentacion:id_presentacion,nombre',
            ])
            ->where('id_sucursal', $branchId)
            ->orderBy('id_salida', 'desc')
            ->select(['id_salida', 'id_sucursal', 'id_usuario', 'motivo', 'observacion', 'fecha_salida', 'estado'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Salidas/Index', [
            'salidas' => $salidas,
        ]);
    }

    public function create(Request $request): Response
    {
        // Se extraen solo las columnas que sí existen en catalogos.productos
        $productos = Producto::query()
            ->where('estado', true)
            ->with([
                'presentaciones' => fn ($query) => $query->where('estado', true)
                    ->with('presentacion:id_presentacion,nombre')
                    ->orderBy('factor_conversion'),
            ])
            ->orderBy('nombre')
            ->get(['id_producto', 'nombre', 'codigo_barras']);

        return Inertia::render('Salidas/Create', [
            'productos' => $productos,
            'motivos' => [
                'MERMA',
                'VENCIMIENTO',
                'DETERIORO / ROTURA',
                'AJUSTE DE INVENTARIO',
                'USO INTERNO',
                'DONACIÓN',
                'OTRO',
            ],
        ]);
    }

    public function store(StoreSalidaRequest $request, RegistrarSalidaService $service): RedirectResponse
    {
        try {
            $service->execute([...$request->validated(), ...$this->operationContext($request)]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['salida' => $exception->getMessage()]);
        }

        return to_route('salidas.index')->with('success', 'Salida registrada y stock dado de baja correctamente.');
    }

    private function operationContext(Request $request): array
    {
        return [
            'id_usuario'  => $request->user()->id_usuario,
            'id_sucursal' => $request->user()->idSucursalActiva(),
        ];
    }
}
