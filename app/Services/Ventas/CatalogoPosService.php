<?php

namespace App\Services\Ventas;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Kardex;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\SerieSucursal;
use App\Models\TerminalPos;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\TurnoCaja;
use App\Services\Configuracion\DatosGeneralesService;
use Illuminate\Database\UniqueConstraintViolationException;
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
    public function datosEmisor(int $branchId): ?Empresa
    {
        return $this->datosGenerales->emisor($branchId);
    }

    public const DOCUMENTO_GENERICO = '00000000';

    /**
     * Cliente "Público en general" para ventas rápidas con boleta. El POS lo selecciona por
     * defecto: si faltara, ninguna venta anónima podría cobrarse. Se crea una sola vez
     * (num_documento es único) y se reactiva si estaba inactivo.
     */
    public function clienteGenerico(): ?Cliente
    {
        $cliente = Cliente::query()->where('num_documento', self::DOCUMENTO_GENERICO)->first();
        if ($cliente) {
            if (! $cliente->estado) {
                $cliente->update(['estado' => true]);
            }

            return $cliente;
        }

        // DNI con ceros, o "sin documento" si el catálogo no tiene DNI activo.
        $tipo = TipoDocumento::query()->where('estado', true)->whereIn('codigo_sunat', [TipoDocumento::DNI, TipoDocumento::SIN_DOCUMENTO])
            ->orderByRaw('CASE WHEN codigo_sunat = ? THEN 0 ELSE 1 END', [TipoDocumento::DNI])->first();
        if (! $tipo) {
            return null;
        }

        try {
            return Cliente::query()->create([
                'id_tipo_documento' => $tipo->id_tipo_documento,
                'num_documento' => self::DOCUMENTO_GENERICO,
                'nombre_razon_social' => 'PÚBLICO EN GENERAL',
                'estado' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otra caja lo creó al mismo tiempo.
            return Cliente::query()->where('num_documento', self::DOCUMENTO_GENERICO)->first();
        }
    }

    /**
     * Lo que falta para poder cobrar en esta sucursal, con dónde resolverlo. Vacío = todo listo.
     * El servidor valida igual al cobrar; esto evita que el botón se bloquee sin explicación.
     *
     * @return array<int, array{clave: string, mensaje: string, enlace: string, accion: string}>
     */
    public function requisitosCobro(int $userId, int $branchId): array
    {
        $faltan = [];
        if (! $this->turnoAbierto($userId, $branchId)) {
            $faltan[] = ['clave' => 'caja', 'mensaje' => 'Abre tu turno de caja.', 'enlace' => '/caja', 'accion' => 'Abrir caja'];
        }
        if (! MetodoPago::query()->where('estado', true)->exists()) {
            $faltan[] = ['clave' => 'metodos_pago', 'mensaje' => 'No hay métodos de pago activos (Efectivo, Yape, tarjeta…).', 'enlace' => '/metodos-pago', 'accion' => 'Agregar'];
        }
        if (! TipoComprobante::query()->where('estado', true)->where('codigo_sunat', TipoComprobante::BOLETA)->exists()) {
            $faltan[] = ['clave' => 'comprobantes', 'mensaje' => 'La boleta (código SUNAT 03) no está habilitada.', 'enlace' => '/tipos-comprobante', 'accion' => 'Habilitar'];
        }
        // Misma fuente que ReservarCorrelativoService: series de la sucursal e IGV de la empresa.
        $serie = SerieSucursal::query()->find($branchId);
        $igv = $this->datosGenerales->empresa()?->igv_porcentaje;
        if (! $serie || blank($serie->serie_boleta) || $igv === null) {
            $faltan[] = ['clave' => 'series', 'mensaje' => 'Falta la serie de boleta o el IGV de esta sucursal.', 'enlace' => '/datos-generales', 'accion' => 'Configurar'];
        }

        return $faltan;
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
        // Orden de alta: el seeder los crea en orden de uso (efectivo, Yape, Plin, tarjetas).
        return MetodoPago::query()->where('estado', true)->orderBy('id_metodo')
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
            ])
            // El Kardex es la fuente de saldo de la sucursal.
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
            'presentaciones' => $presentaciones,
            'stock_actual' => (float) ($producto->stock_actual ?? 0),
            'id_prod_pres_defecto' => $presentacionBase['id_precio'] ?? null,
            'precio_venta' => $presentacionBase['precio_venta'] ?? 0,
        ];
    }
}
