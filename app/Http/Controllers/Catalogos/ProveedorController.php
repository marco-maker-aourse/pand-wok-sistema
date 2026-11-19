<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StoreProveedorRequest;
use App\Models\Ingreso;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProveedorController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('buscar')->trim()->value();

        $proveedores = Proveedor::query()
            ->select(['id_proveedor', 'num_documento', 'razon_social', 'direccion_fiscal', 'telefono', 'email', 'estado'])
            ->buscar($search)
            ->orderBy('razon_social')
            ->paginate(25)
            ->withQueryString();

        $proveedoresConIngresos = Ingreso::query()->distinct()->pluck('id_proveedor');

        return Inertia::render('Proveedores/Index', [
            'proveedores'         => $proveedores,
            'proveedores_con_ingresos'=> $proveedoresConIngresos,
            'filters'             => ['buscar' => $search],
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse|JsonResponse
    {
        $proveedor = DB::transaction(function () use ($request) {
            return Proveedor::create($this->payloadProveedor($request));
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor registrado correctamente.',
                'data'    => $proveedor,
            ], 201);
        }

        return to_route('proveedores.index')->with('success', 'Proveedor registrado correctamente.');
    }

    public function update(StoreProveedorRequest $request, Proveedor $proveedor): RedirectResponse|JsonResponse
    {
        $nuevoRuc = $request->input('ruc');
        $cambioDocumento = $request->filled('ruc') && $proveedor->num_documento !== $nuevoRuc;

        if ($cambioDocumento) {
            abort_unless($request->user()->hasPermission('proveedores.editar_documento'), 403);
            abort_if($proveedor->tieneIngresos(), 403, 'El proveedor ya tiene ingresos asociados.');
            Proveedor::$permitirCambioDocumento = true;
        }

        try {
            DB::transaction(function () use ($request, $proveedor) {
                $data = $request->validated();
                unset($data['motivo_cambio_documento']);
                unset($data['_permitir_cambio_documento']);
                $proveedor->update($this->payloadProveedorFromData($data, $proveedor));
            });
        } finally {
            Proveedor::$permitirCambioDocumento = false;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado correctamente.',
                'data'    => $proveedor->fresh(),
            ]);
        }

        return to_route('proveedores.index')->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse|JsonResponse
    {
        $proveedor->update(['estado' => false]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor desactivado correctamente.',
            ]);
        }

        return to_route('proveedores.index')->with('success', 'Proveedor desactivado del catálogo.');
    }

    /** Traduce el formulario comercial al contrato normalizado de proveedores. */
    private function payloadProveedor(StoreProveedorRequest $request, ?Proveedor $proveedor = null): array
    {
        // El tipo documental es un catálogo administrable; nunca se crea de forma implícita al registrar un proveedor.
        $tipoRuc = TipoDocumento::query()
            ->where('codigo_sunat', '6')
            ->where('estado', true)
            ->firstOrFail();

        return [
            'id_tipo_documento' => $tipoRuc->id_tipo_documento,
            'num_documento' => $request->validated('ruc'),
            'razon_social' => $request->validated('razon_social'),
            'direccion_fiscal' => $request->validated('direccion'),
            'telefono' => $request->validated('telefono'),
            'email' => $request->validated('email'),
            'estado' => $request->has('estado') ? $request->boolean('estado') : ($proveedor?->estado ?? true),
        ];
    }

    /** Construye el payload de actualización a partir de datos ya validados. */
    private function payloadProveedorFromData(array $data, ?Proveedor $proveedor = null): array
    {
        $tipoRuc = TipoDocumento::query()
            ->where('codigo_sunat', '6')
            ->where('estado', true)
            ->firstOrFail();

        return [
            'id_tipo_documento' => $tipoRuc->id_tipo_documento,
            'num_documento' => $data['ruc'],
            'razon_social' => $data['razon_social'],
            'direccion_fiscal' => $data['direccion'],
            'telefono' => $data['telefono'],
            'email' => $data['email'],
            'estado' => $data['estado'] ?? ($proveedor?->estado ?? true),
        ];
    }
}
