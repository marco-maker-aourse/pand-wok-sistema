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
        $this->disminuirBase($precio->producto, $sucursalId, $this->toBaseQuantity($precio, $cantidad), $origen, $codigoLote);
    }

    /** Devuelve el costo y los lotes para que una devolución del restaurante sea exacta. */
    public function disminuirBase(Producto $producto, int $sucursalId, float $cantidadBase, OrigenMovimiento $origen, ?string $codigoLote = null): array
    {
        $ultimoKardex = $this->ultimoKardex($producto->id_producto, $sucursalId);
        $saldo = (float) ($ultimoKardex?->stock_actual ?? 0);

        if ($saldo < $cantidadBase) {
            throw new DomainException("Stock insuficiente para {$producto->nombre}.");
        }

        $lotes = $this->descontarLotes($producto, $sucursalId, $cantidadBase, $codigoLote);
        $costo = (float) ($ultimoKardex?->precio_costo ?? 0);
        // Una salida no recalcula el CPP: conserva el último costo unitario vigente.
        $this->registrarKardex(
            $producto->id_producto, $sucursalId, 0, $cantidadBase, $saldo - $cantidadBase, $costo, $origen,
        );

        return ['costo' => $costo, 'lotes' => $lotes];
    }

    public function increase(ProductoPresentacion $precio, int $sucursalId, float $cantidad, OrigenMovimiento $origen, float $costo = 0): void
    {
        $this->aumentarSaldo($precio->id_producto, $sucursalId, $this->toBaseQuantity($precio, $cantidad), $origen, $costo);
    }

    public function reponerConsumo(int $producto, int $sucursal, float $cantidadBase, float $costo, array $lotes, OrigenMovimiento $origen): void
    {
        $this->aumentarSaldo($producto, $sucursal, $cantidadBase, $origen, $costo);
        foreach ($lotes as $id => $cantidad) {
            $lote = Lote::query()->where('id_producto', $producto)->where('id_sucursal', $sucursal)->lockForUpdate()->find($id);
            if (! $lote || ! $lote->estado || $lote->fecha_vencimiento?->isPast()) {
                throw new DomainException('Uno de los lotes consumidos ya no admite devolución. Registra la anulación como merma.');
            }
            $lote->increment('stock', $cantidad);
        }
    }

    private function aumentarSaldo(int $producto, int $sucursalId, float $cantidadBase, OrigenMovimiento $origen, float $costo): void
    {
        $ultimoKardex = $this->ultimoKardex($producto, $sucursalId);
        $saldoAnterior = (float) ($ultimoKardex?->stock_actual ?? 0);
        $costoAnterior = (float) ($ultimoKardex?->precio_costo ?? 0);
        $saldoNuevo = $saldoAnterior + $cantidadBase;

        // CPP = (saldo anterior × costo anterior + entrada × costo de entrada) / saldo nuevo.
        // Se calcula dentro de la transacción que bloquea el último movimiento del producto/sucursal.
        $costoPromedio = $saldoNuevo > 0
            ? round((($saldoAnterior * $costoAnterior) + ($cantidadBase * $costo)) / $saldoNuevo, 4)
            : 0.0;

        $this->registrarKardex($producto, $sucursalId, $cantidadBase, 0, $saldoNuevo, $costoPromedio, $origen);
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

    private function descontarLotes(Producto $producto, int $sucursalId, float $cantidadBase, ?string $codigoLote): array
    {
        if (! $producto->usa_vencimiento) {
            return [];
        }

        $lotes = Lote::query()
            ->where('id_producto', $producto->id_producto)
            ->where('id_sucursal', $sucursalId)
            ->where('estado', true)
            ->where('stock', '>', 0)
            ->when($codigoLote, fn ($query) => $query->where('codigo_lote', $codigoLote))
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('id_lote')
            ->lockForUpdate()
            ->get();

        $pendiente = $cantidadBase;
        $descontados = [];
        foreach ($lotes as $lote) {
            if ($lote->fecha_vencimiento?->isPast()) {
                continue;
            }

            $descuento = min((float) $lote->stock, $pendiente);
            $lote->decrement('stock', $descuento);
            $descontados[$lote->id_lote] = $descuento;
            $pendiente = round($pendiente - $descuento, 4);

            if ($pendiente <= 0) {
                return $descontados;
            }
        }

        throw new DomainException("Los lotes disponibles no cubren la cantidad solicitada para {$producto->nombre}.");
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
