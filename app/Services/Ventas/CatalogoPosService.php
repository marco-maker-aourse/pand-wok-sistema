<?php

namespace App\Services\Ventas;

use App\Models\DatosGen;
use App\Models\Kardex;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TerminalPos;
use App\Models\TurnoCaja;
use App\Services\Configuracion\DatosGeneralesService;
use Illuminate\Support\Collection;

/**
 * Consultas de solo lectura que alimentan el punto de venta y el historial.
 *
 * Se agrupan aquí para que listado, POS y registro compartan la misma fuente
 * de datos de emisor, turno y catálogo sin repetir consultas en el controlador.
 */
class CatalogoPosService
{
    public function __construct(private readonly DatosGeneralesService $datosGenerales) {}

    /** Datos del emisor de la sucursal, con el código de establecimiento SUNAT. */
    public function datosEmisor(int $branchId): ?DatosGen
    {
        return $this->datosGenerales->emisor($branchId);
    }

    /** Solo se expone el turno del usuario actual: otro cajero no puede operar esta gaveta. */
    public function turnoAbierto(int $userId, int $branchId): ?TurnoCaja
    {
        return TurnoCaja::query()
            ->where('id_usuario', $userId)
            ->where('id_sucursal', $branchId)
            ->where('estado', 'ABIERTO')
            ->first(['id_turno', 'fecha_apertura', 'monto_apertura']);
    }

    /** Terminales de la sucursal disponibles al abrir un nuevo turno desde el POS. */
    public function terminalesActivas(int $branchId): array
    {
        return TerminalPos::query()
            ->where('id_sucursal', $branchId)
            ->where('activo', true)
            ->whereHas('impresora', fn ($query) => $query->where('activo', true))
            ->with('impresora:id_impresora,nombre,estado_conexion,activo')
            ->orderBy('nombre')
            ->get(['id_terminal', 'id_sucursal', 'id_impresora', 'codigo', 'nombre', 'impresion_automatica'])
            ->all();
    }

    /** Métodos activos para cobro presencial; el nombre proviene del catálogo. */
    public function metodosPago(): Collection
    {
        return MetodoPago::query()->where('estado', true)->orderBy('nombre')
            ->get(['id_metodo', 'nombre', 'tipo', 'requiere_referencia'])
            ->map(fn (MetodoPago $metodo): array => [
                'id_metodo' => $metodo->id_metodo,
                'nombre' => $metodo->nombre,
                'tipo' => $metodo->tipo,
                'requiere_referencia' => (bool) $metodo->requiere_referencia,
            ])->values();
    }

    /** Catálogo vendible de la sucursal, filtrado por nombre o código y con límite estricto. */
    public function productos(int $branchId, ?string $term, int $limit): array
    {
        return Producto::query()
            ->where('estado', true)
            ->select([
                'catalogos.productos.id_producto',
                'catalogos.productos.nombre',
                'catalogos.productos.codigo_barras',
                'catalogos.productos.id_unidad_base',
                'catalogos.productos.afecta_igv',
                'catalogos.productos.es_servicio',
                'catalogos.productos.usa_vencimiento',
            ])
            // El Kardex es la fuente de saldo de la sucursal. Los lotes solo complementan
            // el control FIFO para productos que manejan vencimiento.
            ->selectSub(
                Kardex::query()
                    ->select('stock_actual')
                    ->whereColumn('id_producto', 'catalogos.productos.id_producto')
                    ->where('id_sucursal', $branchId)
                    ->latest('id_kardex')
                    ->limit(1),
                'stock_actual',
            )
            ->with([
                'unidadBase:id_unidad,nombre',
                'presentaciones' => fn ($query) => $query
                    ->select(['id_precio', 'id_producto', 'id_presentacion', 'factor_conversion', 'precio_venta', 'estado'])
                    ->where('estado', true)
                    ->with('presentacion:id_presentacion,nombre')
                    ->orderBy('factor_conversion'),
                'lotes' => fn ($query) => $query
                    ->where('id_sucursal', $branchId)
                    ->where('estado', true)
                    ->where('stock', '>', 0)
                    ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
                    ->orderBy('id_lote'),
            ])
            ->whereHas('presentaciones', fn ($query) => $query->where('estado', true))
            ->when($term, fn ($query, string $search) => $query->where(function ($filter) use ($search) {
                $filter->where('catalogos.productos.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('catalogos.productos.codigo_barras', 'ILIKE', "%{$search}%");
            }))
            ->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn (Producto $producto): array => $this->productoParaPos($producto))
            ->values()
            ->all();
    }

    private function productoParaPos(Producto $producto): array
    {
        $presentaciones = $producto->presentaciones->map(function (ProductoPresentacion $precio): array {
            $factor = (float) $precio->factor_conversion;

            return [
                'id_precio' => $precio->id_precio,
                // Compatibilidad con el contrato anterior del POS.
                'id_prod_pres' => $precio->id_precio,
                'nombre' => $precio->presentacion?->nombre ?? 'Unidad',
                'factor_conversion' => $factor,
                'precio_venta' => (float) $precio->precio_venta,
                // La presentación de una unidad es venta minorista; un empaque
                // con varias unidades es venta mayorista según el modelo aprobado.
                'modalidad' => $factor > 1 ? 'MAYORISTA' : 'MINORISTA',
            ];
        })->values();
        // La presentación de factor 1 es la venta al detalle. Si el catálogo no
        // la configuró, se usa la menor conversión disponible como alternativa.
        $presentacionBase = $presentaciones->firstWhere('factor_conversion', 1)
            ?? $presentaciones->first();

        return [
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'codigo_barras' => $producto->codigo_barras,
            'unidad_base' => $producto->unidadBase ? [
                'id_unidad' => $producto->unidadBase->id_unidad,
                'nombre' => $producto->unidadBase->nombre,
            ] : null,
            'afecta_igv' => $producto->afecta_igv,
            'es_servicio' => $producto->es_servicio,
            'usa_vencimiento' => $producto->usa_vencimiento,
            'presentaciones' => $presentaciones,
            'lotes' => $producto->lotes->values(),
            'stock_actual' => (float) ($producto->stock_actual ?? 0),
            'lote_recomendado' => $producto->lotes->first(),
            'id_prod_pres_defecto' => $presentacionBase['id_precio'] ?? null,
            'precio_venta' => $presentacionBase['precio_venta'] ?? 0,
        ];
    }
}
