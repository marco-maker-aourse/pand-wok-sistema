<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;

use App\Http\Requests\Operaciones\StoreSalidaRequest;
use App\Models\Kardex;
use App\Models\Producto;
use App\Models\Salida;
use App\Services\Inventario\TipoExistencia;
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
        $idSucursal = $request->user()->idSucursalActiva();

        // Saldo vigente de la sucursal = último movimiento de kardex de cada producto.
        $saldos = Kardex::query()
            ->whereIn('id_kardex', Kardex::query()->selectRaw('MAX(id_kardex)')->where('id_sucursal', $idSucursal)->groupBy('id_producto'))
            ->pluck('stock_actual', 'id_producto');

        // Los platos preparados no tienen stock propio (consumen insumos por receta).
        $productos = Producto::query()
            ->where('estado', true)
            ->where('tipo_producto', '!=', Producto::PREPARADO)
            ->with([
                'unidadBase:id_unidad,nombre',
                'presentaciones' => fn ($query) => $query->where('estado', true)
                    ->with(['presentacion:id_presentacion,nombre', 'productoStock:id_producto,nombre'])
                    ->orderBy('factor_conversion'),
            ])
            ->orderBy('nombre')
            ->get(['id_producto', 'nombre', 'codigo_barras', 'id_unidad_base', 'tipo_producto'])
            ->map(fn (Producto $producto): array => [
                ...$producto->toArray(),
                'stock_actual' => (float) ($saldos[$producto->id_producto] ?? 0),
                ...TipoExistencia::de($producto),
            ]);

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
