<?php

namespace App\Services\Inventario;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\Configuracion\DatosGeneralesService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kardex valorizado de un producto en una sucursal y un periodo (método: costo promedio
 * ponderado). Es la única fuente de datos para la pantalla, el Excel y el PDF: ninguno de
 * ellos recalcula importes.
 *
 * Cada fila de inventario.kardex guarda el saldo y el costo promedio DESPUÉS del movimiento.
 * A partir de dos filas consecutivas se obtiene, sin datos adicionales:
 *  - Entrada: costo total = saldo nuevo valorizado − saldo anterior valorizado.
 *  - Salida: se valoriza al costo promedio vigente, que la propia fila conserva.
 */
class KardexValorizadoService
{
    /** Más filas que esto en un periodo es un rango demasiado amplio para un reporte legible. */
    public const MAXIMO_MOVIMIENTOS = 5000;

    public const ZONA = 'America/Lima';

    private const ETIQUETAS = [
        OrigenMovimiento::VENTA => 'Venta',
        OrigenMovimiento::INGRESO => 'Compra / ingreso',
        OrigenMovimiento::AJUSTE => 'Ajuste de inventario',
        OrigenMovimiento::CONSUMO_RECETA => 'Consumo de receta',
        OrigenMovimiento::REVERSION_RECETA => 'Devolución de receta',
        OrigenMovimiento::MERMA => 'Merma',
    ];

    public function __construct(private readonly DatosGeneralesService $datosGenerales = new DatosGeneralesService) {}

    /** Primer día del mes actual a hoy, en hora de Perú. */
    public static function periodoPorDefecto(): array
    {
        $hoy = CarbonImmutable::now(self::ZONA);

        return [$hoy->startOfMonth(), $hoy];
    }

    public function generar(Producto $producto, int $sucursalId, CarbonInterface $desde, CarbonInterface $hasta, ?string $generadoPor = null): array
    {
        $inicio = CarbonImmutable::parse($desde->format('Y-m-d'), self::ZONA)->startOfDay();
        $fin = CarbonImmutable::parse($hasta->format('Y-m-d'), self::ZONA)->endOfDay();
        $conOrigen = Kardex::registraOrigen();

        $base = fn () => Kardex::withoutGlobalScopes()
            ->where('id_producto', $producto->id_producto)
            ->where('id_sucursal', $sucursalId);

        $anterior = $base()
            ->where('ultima_actualizacion', '<', $inicio->utc())
            ->orderByDesc('id_kardex')
            ->first();

        $filas = $base()
            ->whereBetween('ultima_actualizacion', [$inicio->utc(), $fin->utc()])
            ->when($conOrigen, fn ($q) => $q->with('usuario:id_usuario,nombres'))
            ->orderBy('id_kardex')
            ->limit(self::MAXIMO_MOVIMIENTOS + 1)
            ->get();

        $truncado = $filas->count() > self::MAXIMO_MOVIMIENTOS;
        $filas = $filas->take(self::MAXIMO_MOVIMIENTOS);

        $producto->loadMissing('unidadBase:id_unidad,nombre');
        $emisor = $this->datosGenerales->emisor($sucursalId);

        return [
            'empresa' => [
                'razon_social' => $emisor?->razon_social,
                'ruc' => $emisor?->ruc_empresa,
                'sucursal' => Sucursal::query()->whereKey($sucursalId)->value('nombre'),
            ],
            'producto' => [
                'id_producto' => $producto->id_producto,
                'codigo' => $producto->codigo_barras,
                'nombre' => $producto->nombre,
                'unidad' => $producto->unidadBase?->nombre ?? 'UNIDAD',
            ],
            'periodo' => [
                'desde' => $inicio->toDateString(),
                'hasta' => $fin->toDateString(),
            ],
            'metodo' => 'Costo promedio ponderado',
            'con_origen' => $conOrigen,
            'truncado' => $truncado,
            'generado' => [
                'en' => CarbonImmutable::now(self::ZONA)->format('d/m/Y H:i'),
                'por' => $generadoPor,
            ],
            ...self::armar(
                $anterior ? self::filaPlana($anterior) : null,
                $filas->map(fn (Kardex $fila) => self::filaPlana($fila))->all(),
            ),
        ];
    }

    /**
     * Cálculo puro del kardex valorizado (sin base de datos).
     *
     * @param  array|null  $anterior  último movimiento antes del periodo (define el saldo inicial)
     * @param  array<int, array>  $filas  movimientos del periodo en orden de registro
     */
    public static function armar(?array $anterior, array $filas): array
    {
        $saldoCantidad = $anterior ? $anterior['stock'] : 0.0;
        $saldoCosto = $anterior ? $anterior['costo'] : 0.0;

        $inicial = self::saldo($saldoCantidad, $saldoCosto);
        $totales = ['entradas_cantidad' => 0.0, 'entradas_total' => 0.0, 'salidas_cantidad' => 0.0, 'salidas_total' => 0.0];
        $movimientos = [];

        foreach ($filas as $fila) {
            $valorAnterior = $saldoCantidad * $saldoCosto;
            $entrada = null;
            $salida = null;

            if ($fila['entrada'] > 0) {
                $total = $fila['stock'] * $fila['costo'] - $valorAnterior;
                $entrada = ['cantidad' => $fila['entrada'], 'costo_unitario' => round($total / $fila['entrada'], 4), 'total' => round($total, 2)];
                $totales['entradas_cantidad'] += $fila['entrada'];
                $totales['entradas_total'] += $total;
            }

            if ($fila['salida'] > 0) {
                // Una salida no cambia el promedio: la fila guarda el costo vigente al salir.
                $costo = $fila['costo'];
                $total = $fila['salida'] * $costo;
                $salida = ['cantidad' => $fila['salida'], 'costo_unitario' => round($costo, 4), 'total' => round($total, 2)];
                $totales['salidas_cantidad'] += $fila['salida'];
                $totales['salidas_total'] += $total;
            }

            $saldoCantidad = $fila['stock'];
            $saldoCosto = $fila['costo'];

            $movimientos[] = [
                'id_kardex' => $fila['id'],
                'fecha' => $fila['fecha'],
                'tipo' => $fila['tipo'] ?? ($fila['entrada'] > 0 ? 'ENTRADA' : 'SALIDA'),
                'operacion' => self::ETIQUETAS[$fila['tipo'] ?? ''] ?? ($fila['entrada'] > 0 ? 'Entrada' : 'Salida'),
                'documento' => $fila['documento'],
                'id_referencia' => $fila['id_referencia'],
                'usuario' => $fila['usuario'],
                'entrada' => $entrada,
                'salida' => $salida,
                'saldo' => self::saldo($saldoCantidad, $saldoCosto),
            ];
        }

        return [
            'saldo_inicial' => $inicial,
            'movimientos' => $movimientos,
            'totales' => [
                'entradas_cantidad' => round($totales['entradas_cantidad'], 4),
                'entradas_total' => round($totales['entradas_total'], 2),
                'salidas_cantidad' => round($totales['salidas_cantidad'], 4),
                'salidas_total' => round($totales['salidas_total'], 2),
            ],
            'saldo_final' => self::saldo($saldoCantidad, $saldoCosto),
        ];
    }

    private static function saldo(float $cantidad, float $costo): array
    {
        return ['cantidad' => round($cantidad, 4), 'costo_unitario' => round($costo, 4), 'total' => round($cantidad * $costo, 2)];
    }

    private static function filaPlana(Kardex $fila): array
    {
        $conOrigen = Kardex::registraOrigen();

        return [
            'id' => (int) $fila->id_kardex,
            'fecha' => $fila->ultima_actualizacion?->copy()->setTimezone(self::ZONA)->format('d/m/Y H:i'),
            'entrada' => (float) $fila->cantidad_entrada,
            'salida' => (float) $fila->cantidad_salida,
            'stock' => (float) $fila->stock_actual,
            'costo' => (float) $fila->precio_costo,
            'tipo' => $conOrigen ? $fila->tipo_operacion : null,
            'documento' => $conOrigen ? $fila->documento : null,
            'id_referencia' => $conOrigen && $fila->id_referencia ? (int) $fila->id_referencia : null,
            'usuario' => $conOrigen ? $fila->usuario?->nombres : null,
        ];
    }
}
