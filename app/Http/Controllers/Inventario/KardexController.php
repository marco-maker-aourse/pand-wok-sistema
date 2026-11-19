<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;

use App\Models\Kardex;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KardexController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()->idSucursalActiva();

        $productos = Producto::query()
            ->where('estado', true)
            // PostgreSQL calcula el saldo de lotes: evita sumar colecciones hidratadas en PHP.
            ->withSum(['lotes as stock_lotes' => fn ($q) => $q->where('id_sucursal', $branchId)->where('stock', '>', 0)], 'stock')
            ->with([
                'unidadBase:id_unidad,nombre',
                'presentaciones' => fn ($q) => $q->where('estado', true)
                    ->with('presentacion:id_presentacion,nombre')
                    ->orderBy('factor_conversion', 'asc'),
                'lotes' => fn ($q) => $q->where('id_sucursal', $branchId)
                    ->where('stock', '>', 0)
                    ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
                    ->orderBy('id_lote', 'asc'),
                'kardex' => fn ($q) => $q->where('id_sucursal', $branchId)
                    ->orderBy('id_kardex', 'desc')
                    ->take(30),
            ])
            ->orderBy('nombre')
            ->get()
            ->map(function ($prod) {
                // Stock total calculado de los lotes vigentes o saldo en Kardex
                $stockCalculado = (float) ($prod->stock_lotes ?? 0);
                $ultimoKardex = $prod->kardex->first();
                $saldoStock = $ultimoKardex ? (float) $ultimoKardex->stock_actual : $stockCalculado;

                // Formateamos las presentaciones con sus nombres y PVP reales
                $presentacionesFormateadas = $prod->presentaciones->map(function ($p) {
                    return [
                    'id_prod_pres'      => $p->id_precio,
                        'id_presentacion'   => $p->id_presentacion,
                        'nombre'            => $p->presentacion ? $p->presentacion->nombre : 'Unidad Base',
                        'factor_conversion' => (float) $p->factor_conversion,
                        'precio_venta'      => (float) $p->precio_venta,
                    ];
                });

                $movimientos = $prod->kardex->map(fn (Kardex $movimiento) => [
                    'id_kardex' => $movimiento->id_kardex,
                    'cantidad_entrada' => (float) $movimiento->cantidad_entrada,
                    'cantidad_salida' => (float) $movimiento->cantidad_salida,
                    'stock_actual' => (float) $movimiento->stock_actual,
                    'precio_costo' => (float) $movimiento->precio_costo,
                    'ultima_actualizacion' => $movimiento->ultima_actualizacion,
                ])->values();

                return [
                    'id_producto'    => $prod->id_producto,
                    'nombre'         => $prod->nombre,
                    'codigo_barras'  => $prod->codigo_barras,
                    // Contrato explícito para las pantallas: el saldo siempre está
                    // expresado en la unidad base del producto y de esta sucursal.
                    'stock_actual'   => $saldoStock,
                    'unidad_base'    => $prod->unidadBase ? [
                        'id_unidad' => $prod->unidadBase->id_unidad,
                        'nombre' => $prod->unidadBase->nombre,
                    ] : null,
                    'precio_costo' => (float) ($ultimoKardex?->precio_costo ?? 0),
                    'ultima_actualizacion' => $ultimoKardex?->ultima_actualizacion,
                    'presentaciones' => $presentacionesFormateadas,
                    'lotes'          => $prod->lotes,
                    'movimientos'    => $movimientos,
                ];
            });

        return Inertia::render('Kardex/Index', [
            'productos' => $productos,
        ]);
    }

    /**
     * Ajusta el stock físico y actualiza el precio (PVP) de cada presentación seleccionada
     */
    public function ajustarStockYPrecio(Request $request, GestionarExistenciaService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'id_producto'         => ['required', 'integer', Rule::exists(Producto::class, 'id_producto')],
            'nuevo_stock'         => ['required', 'numeric', 'min:0'],
            'motivo'              => ['required', 'string', 'max:255'],
            'codigo_lote'         => ['nullable', 'string', 'max:50'],
            'fecha_vencimiento'   => ['nullable', 'date'],
            'precios'             => ['required', 'array', 'min:1'],
            'precios.*.id_prod_pres' => ['required', 'integer', Rule::exists(ProductoPresentacion::class, 'id_precio')],
            'precios.*.precio_venta' => ['required', 'numeric', 'min:0'],
        ]);

        $branchId = $request->user()->idSucursalActiva();
        $userId = (int) $request->user()->id_usuario;

        DB::transaction(function () use ($validated, $branchId, $userId, $inventory) {
            // Las presentaciones deben pertenecer al producto ajustado. Evita alterar
            // precios de otro artículo mediante una petición HTTP manipulada.
            $presentacionIds = collect($validated['precios'])->pluck('id_prod_pres')->unique()->values();
            $presentaciones = ProductoPresentacion::query()
                ->where('id_producto', $validated['id_producto'])
                ->whereIn('id_precio', $presentacionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id_precio');

            if ($presentaciones->count() !== $presentacionIds->count()) {
                throw ValidationException::withMessages([
                    'precios' => 'Una presentación no corresponde al producto que se está ajustando.',
                ]);
            }

            foreach ($validated['precios'] as $item) {
                $presentaciones->get($item['id_prod_pres'])->update([
                    'precio_venta' => (float) $item['precio_venta'],
                ]);
            }

            // 2. Obtener saldo actual en Kardex, serializado por producto igual que ventas e ingresos.
            $inventory->bloquearProducto((int) $validated['id_producto']);
            $ultimoKardex = Kardex::query()
                ->where('id_producto', $validated['id_producto'])
                ->where('id_sucursal', $branchId)
                ->orderBy('id_kardex', 'desc')
                ->lockForUpdate()
                ->first();

            $stockAnterior = $ultimoKardex ? (float) $ultimoKardex->stock_actual : 0.0;
            $nuevoStock = (float) $validated['nuevo_stock'];
            $diferencia = $nuevoStock - $stockAnterior;

            $cantEntrada = $diferencia > 0 ? $diferencia : 0;
            $cantSalida = $diferencia < 0 ? abs($diferencia) : 0;

            // 3. Registrar el movimiento oficial en Kardex con las columnas reales de Supabase
            Kardex::create([
                ...OrigenMovimiento::ajuste($validated['motivo'], $userId)->columnas(),
                'id_sucursal'          => $branchId,
                'id_producto'          => $validated['id_producto'],
                'cantidad_entrada'     => $cantEntrada,
                'cantidad_salida'      => $cantSalida,
                'stock_actual'         => $nuevoStock,
                'precio_costo'         => $ultimoKardex ? $ultimoKardex->precio_costo : 0,
                'porcentaje_utilidad'  => 0,
                'ultima_actualizacion' => now(),
            ]);

            // 4. Actualizar o crear lote FIFO con columnas exactas de inventario.lotes
            $codLote = filled($validated['codigo_lote'] ?? null)
                ? $validated['codigo_lote']
                : 'LOTE-AJUSTE-' . date('Ymd');

            $lote = Lote::query()
                ->where('id_producto', $validated['id_producto'])
                ->where('id_sucursal', $branchId)
                ->where('codigo_lote', $codLote)
                ->first();

            if ($lote) {
                $lote->update([
                    'stock'             => $nuevoStock,
                    'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? $lote->fecha_vencimiento,
                    'estado'            => $nuevoStock > 0,
                ]);
            } else {
                Lote::create([
                    'id_producto'       => $validated['id_producto'],
                    'id_sucursal'       => $branchId,
                    'codigo_lote'       => $codLote,
                    'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                    'stock'             => $nuevoStock,
                    'estado'            => $nuevoStock > 0,
                ]);
            }
        });

        return to_route('kardex.index')->with('success', 'Stock y precios por presentación actualizados.');
    }
}
