<?php

namespace App\Services\Inventario;

use App\Models\Kardex;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use DomainException;

class GestionarExistenciaService
{
    /** El saldo está en Kardex; los lotes detallan el stock con vencimiento. */
    public function decrease(ProductoPresentacion $precio, int $sucursalId, float $cantidad, OrigenMovimiento $origen, ?string $codigoLote = null): void
    {
        $cantidadBase = $this->toBaseQuantity($precio, $cantidad);
        $ultimoKardex = $this->ultimoKardex($precio->id_producto, $sucursalId);
        $saldo = (float) ($ultimoKardex?->stock_actual ?? 0);

        if ($saldo < $cantidadBase) {
            throw new DomainException("Stock insuficiente para {$precio->producto->nombre}.");
        }

        $this->descontarLotes($precio, $sucursalId, $cantidadBase, $codigoLote);
        // Una salida no recalcula el CPP: conserva el último costo unitario vigente.
        $this->registrarKardex(
            $precio->id_producto,
            $sucursalId,
            0,
            $cantidadBase,
            $saldo - $cantidadBase,
            (float) ($ultimoKardex?->precio_costo ?? 0),
            $origen,
        );
    }

    public function increase(ProductoPresentacion $precio, int $sucursalId, float $cantidad, OrigenMovimiento $origen, float $costo = 0): void
    {
        $cantidadBase = $this->toBaseQuantity($precio, $cantidad);
        $ultimoKardex = $this->ultimoKardex($precio->id_producto, $sucursalId);
        $saldoAnterior = (float) ($ultimoKardex?->stock_actual ?? 0);
        $costoAnterior = (float) ($ultimoKardex?->precio_costo ?? 0);
        $saldoNuevo = $saldoAnterior + $cantidadBase;

        // CPP = (saldo anterior × costo anterior + entrada × costo de entrada) / saldo nuevo.
        // Se calcula dentro de la transacción que bloquea el último movimiento del producto/sucursal.
        $costoPromedio = $saldoNuevo > 0
            ? round((($saldoAnterior * $costoAnterior) + ($cantidadBase * $costo)) / $saldoNuevo, 4)
            : 0.0;

        $this->registrarKardex($precio->id_producto, $sucursalId, $cantidadBase, 0, $saldoNuevo, $costoPromedio, $origen);
    }

    public function toBaseQuantity(ProductoPresentacion $precio, float $cantidad): float
    {
        return round($cantidad * (float) $precio->factor_conversion, 4);
    }

    /**
     * Serializa los movimientos de un producto bloqueando su fila maestra.
     *
     * Bloquear solo el último Kardex no basta en PostgreSQL (READ COMMITTED):
     * la transacción que espera recibe la fila antigua y no ve el movimiento
     * insertado por la otra, calculando el saldo sobre un valor obsoleto. Si el
     * producto aún no tiene Kardex, tampoco habría fila que bloquear.
     */
    public function bloquearProducto(int $productoId): void
    {
        Producto::query()->whereKey($productoId)->lockForUpdate()->first(['id_producto']);
    }

    private function ultimoKardex(int $productoId, int $sucursalId): ?Kardex
    {
        $this->bloquearProducto($productoId);

        return Kardex::query()
            ->where('id_producto', $productoId)
            ->where('id_sucursal', $sucursalId)
            ->latest('id_kardex')
            ->lockForUpdate()
            ->first();
    }

    private function descontarLotes(ProductoPresentacion $precio, int $sucursalId, float $cantidadBase, ?string $codigoLote): void
    {
        if (! $precio->producto->usa_vencimiento) {
            return;
        }

        $lotes = Lote::query()
            ->where('id_producto', $precio->id_producto)
            ->where('id_sucursal', $sucursalId)
            ->where('estado', true)
            ->where('stock', '>', 0)
            ->when($codigoLote, fn ($query) => $query->where('codigo_lote', $codigoLote))
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('id_lote')
            ->lockForUpdate()
            ->get();

        $pendiente = $cantidadBase;
        foreach ($lotes as $lote) {
            if ($lote->fecha_vencimiento?->isPast()) {
                continue;
            }

            $descuento = min((float) $lote->stock, $pendiente);
            $lote->decrement('stock', $descuento);
            $pendiente -= $descuento;

            if ($pendiente <= 0) {
                return;
            }
        }

        throw new DomainException("Los lotes disponibles no cubren la cantidad solicitada para {$precio->producto->nombre}.");
    }

    private function registrarKardex(int $productoId, int $sucursalId, float $entrada, float $salida, float $saldo, float $costo, OrigenMovimiento $origen): void
    {
        Kardex::create([
            ...$origen->columnas(),
            'id_producto' => $productoId,
            'id_sucursal' => $sucursalId,
            'cantidad_entrada' => $entrada,
            'cantidad_salida' => $salida,
            'stock_actual' => $saldo,
            'precio_costo' => $costo,
            'ultima_actualizacion' => now(),
        ]);
    }
}
