<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\StoreProductoRequest;
use App\Http\Requests\Catalogos\UpdateProductoRequest;
use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Services\Productos\RegistrarProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        $productos = Producto::query()
            ->where('estado', true)
            ->with([
                'categoria:id_categoria,nombre',
                'unidadBase:id_unidad,nombre,codigo_sunat',
                'presentaciones.presentacion:id_presentacion,nombre',
            ])
            ->withExists([
                'kardex as tiene_entrada_registrada' => fn ($query) => $query
                    ->where('id_sucursal', $branchId)
                    ->where('cantidad_entrada', '>', 0),
            ])
            ->orderBy('id_producto', 'desc')
            ->get();

        return Inertia::render('Productos/Index', [
            'productos'              => $productos,
            'categorias'             => Categoria::where('estado', true)->orderBy('nombre')->get(['id_categoria', 'nombre']),
            'unidades'               => UnidadMedida::orderBy('nombre')->get(['id_unidad', 'nombre', 'codigo_sunat']),
            'catalogoPresentaciones' => Presentacion::where('estado', true)->orderBy('nombre')->get(['id_presentacion', 'nombre']),
        ]);
    }

    public function show(Request $request, Producto $producto): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        $producto->load([
            'categoria:id_categoria,nombre',
            'marca:id_marca,nombre',
            'unidadBase:id_unidad,nombre,codigo_sunat',
            'presentaciones' => fn ($query) => $query
                ->where('estado', true)
                ->with('presentacion:id_presentacion,nombre')
                ->orderBy('factor_conversion'),
        ]);

        $existencia = $producto->kardex()
            ->where('id_sucursal', $branchId)
            // El último movimiento registrado es el saldo vigente, igual que en Kardex y POS.
            ->latest('id_kardex')
            ->first([
                'id_kardex',
                'stock_actual',
                'precio_costo',
                'porcentaje_utilidad',
                'ultima_actualizacion',
            ]);

        return Inertia::render('Productos/Show', [
            'producto'   => $producto,
            'existencia' => $existencia,
        ]);
    }

    public function store(StoreProductoRequest $request, RegistrarProductoService $registrar): RedirectResponse
    {
        $registrar->execute($request->datosProducto());

        return redirect()->route('productos.index')->with('success', 'Producto y presentaciones registrados correctamente.');
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->datosProducto());

        return redirect()->route('productos.index')->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['estado' => false]);

        return redirect()->route('productos.index')->with('success', 'Producto desactivado del inventario.');
    }

    public function storePresentacion(Request $request, Producto $producto): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'id_presentacion'   => ['required', 'integer', Rule::exists(Presentacion::class, 'id_presentacion')],
            'factor_conversion' => ['required', 'numeric', 'min:0.0001'],
            'precio_venta'      => ['required', 'numeric', 'min:0'],
        ], [
            'id_presentacion.required'   => 'Debe seleccionar un tipo de empaque.',
            'factor_conversion.required' => 'Indique cuántas unidades contiene este empaque.',
            'precio_venta.required'      => 'Indique el precio de venta sugerido.',
        ]);

        $productoPresentacion = ProductoPresentacion::updateOrCreate(
            [
                'id_producto'     => $producto->id_producto,
                'id_presentacion' => $validated['id_presentacion'],
            ],
            [
                'factor_conversion' => $validated['factor_conversion'],
                'precio_venta'      => $validated['precio_venta'],
                'estado'            => true,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $productoPresentacion->load('presentacion:id_presentacion,nombre'),
            ]);
        }

        return back()->with('success', 'Presentación y precio sugerido asignados correctamente.');
    }

    public function destroyPresentacion(Request $request, ProductoPresentacion $productoPresentacion): RedirectResponse|JsonResponse
    {
        $productoPresentacion->delete();

        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        return back()->with('success', 'Empaque retirado del producto.');
    }
}