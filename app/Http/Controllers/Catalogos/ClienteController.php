<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;

use App\Http\Requests\Catalogos\StoreClienteRequest;
use App\Models\Cliente;
use App\Models\TipoDocumento;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClienteController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('buscar')->trim()->value();

        $clientes = Cliente::query()
            ->with('tipoDocumento:id_tipo_documento,codigo_sunat,nombre,longitud_exacta,tipo_persona')
            ->buscar($search)
            ->orderBy('id_cliente', 'desc')
            ->paginate(25)
            ->withQueryString();

        $clientesConVentas = Venta::query()->distinct()->pluck('id_cliente');

        return Inertia::render('Clientes/Index', [
            'clientes'           => $clientes,
            'tiposDocumento'     => TipoDocumento::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id_tipo_documento', 'codigo_sunat', 'nombre', 'longitud_exacta', 'tipo_persona']),
            'clientes_con_ventas'=> $clientesConVentas,
            'filters'            => ['buscar' => $search],
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse|JsonResponse
    {
        $cliente = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['estado'] = $request->boolean('estado', true);
            return Cliente::create($data);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado correctamente.',
                'data'    => $cliente,
            ], 201);
        }

        return to_route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function update(StoreClienteRequest $request, Cliente $cliente): RedirectResponse|JsonResponse
    {
        $cambioDocumento = $request->filled('num_documento') && $cliente->num_documento !== $request->input('num_documento');

        if ($cambioDocumento) {
            abort_unless($request->user()->hasPermission('clientes.editar_documento'), 403);
            abort_if($cliente->tieneVentas(), 403, 'El cliente ya tiene comprobantes emitidos.');
            Cliente::$permitirCambioDocumento = true;
        }

        try {
            DB::transaction(function () use ($request, $cliente) {
                $data = $request->validated();
                unset($data['motivo_cambio_documento']);
                unset($data['_permitir_cambio_documento']);
                if ($request->has('estado')) {
                    $data['estado'] = $request->boolean('estado');
                }
                $cliente->update($data);
            });
        } finally {
            Cliente::$permitirCambioDocumento = false;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente.',
                'data'    => $cliente->fresh(),
            ]);
        }

        return to_route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse|JsonResponse
    {
        $cliente->update(['estado' => false]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente desactivado correctamente.',
            ]);
        }

        return to_route('clientes.index')->with('success', 'Cliente desactivado del catálogo.');
    }
}
