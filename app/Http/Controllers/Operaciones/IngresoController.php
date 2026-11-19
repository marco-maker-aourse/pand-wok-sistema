<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operaciones\StoreIngresoRequest;
use App\Models\Ingreso;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TipoComprobante;
use App\Services\Ingresos\RegistrarIngresoService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IngresoController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        $ingresos = Ingreso::query()
            ->with([
                'proveedor:id_proveedor,razon_social,num_documento,telefono',
                'detalles.precio.producto:id_producto,nombre,codigo_barras',
                'detalles.precio.presentacion:id_presentacion,nombre',
            ])
            ->where('id_sucursal', $branchId)
            ->orderBy('id_ingreso', 'desc')
            ->select([
                'id_ingreso', 'id_sucursal', 'id_proveedor', 'id_tipo_comprobante',
                'serie_comprobante', 'num_comprobante', 'fecha_emision',
                'impuesto_total', 'total_ingreso', 'estado'
            ])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Ingresos/Index', [
            'ingresos' => $ingresos,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Ingresos/Create', [
            'proveedores' => Proveedor::query()
                ->where('estado', true)
                ->orderBy('razon_social')
                ->get([
                    'id_proveedor',
                    'num_documento',
                    'num_documento as ruc',
                    'razon_social',
                    'direccion_fiscal'
                ]),

            'productos' => Producto::query()
                ->where('estado', true)
                ->with([
                    'unidadBase:id_unidad,nombre',
                    'presentaciones' => fn ($query) => $query->where('estado', true)
                        ->with('presentacion:id_presentacion,nombre')
                        ->orderBy('factor_conversion')
                ])
                ->orderBy('nombre')
                ->get(['id_producto', 'nombre', 'codigo_barras', 'id_unidad_base']),

            'tipos_comprobante' => TipoComprobante::query()
                ->where('estado', true)
                ->orderBy('descripcion')
                ->get(['id_tipo_comprobante', 'codigo_sunat', 'descripcion']),

            'productoInicialId' => $request->integer('producto'),
        ]);
    }

    public function store(StoreIngresoRequest $request, RegistrarIngresoService $service): RedirectResponse
    {
        try {
            $service->execute([
                ...$request->validated(),
                ...$this->operationContext($request)
            ]);
        } catch (ModelNotFoundException $exception) {
            throw ValidationException::withMessages([
                'ingreso' => 'No se encontró alguno de los elementos seleccionados (proveedor, producto o presentación).'
            ]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'ingreso' => $exception->getMessage()
            ]);
        }

        return to_route('ingresos.index')->with('success', 'Compra registrada y stock actualizado en almacén.');
    }

    private function operationContext(Request $request): array
    {
        return [
            'id_usuario'  => $request->user()->id_usuario,
            'id_sucursal' => $request->user()->idSucursalActiva(),
        ];
    }
}