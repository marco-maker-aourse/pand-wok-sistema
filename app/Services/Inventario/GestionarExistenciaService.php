<?php

namespace App\Services\Inventario;

use App\Models\Kardex;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use DomainException;

class GestionarExistenciaService
{
    /** El saldo de cada producto por sucursal está en el Kardex. */
    public function decrease(ProductoPresentacion $precio, int $sucursalId, float $cantidad, OrigenMovimiento $origen): void
    {
        // Una variante física (id_producto_stock) descuenta su propio inventario.
        $this->disminuirBase($precio->productoInventario(), $sucursalId, $this->toBaseQuantity($precio, $cantidad), $origen);
    }

    /** Devuelve el costo unitario aplicado para que una devolución del restaurante sea exacta. */
    public function disminuirBase(Producto $producto, int $sucursalId, float $cantidadBase, OrigenMovimiento $origen): float
    {
        $ultimoKardex = $this->ultimoKardex($producto->id_producto, $sucursalId);
        $saldo = (float) ($ultimoKardex?->stock_actual ?? 0);

        if ($saldo < $cantidadBase) {
            throw new DomainException("Stock insuficiente para {$producto->nombre}.");
        }

        $costo = (float) ($ultimoKardex?->precio_costo ?? 0);
        // Una salida no recalcula el CPP: conserva el último costo unitario vigente.
        $this->registrarKardex(
            $producto->id_producto, $sucursalId, 0, $cantidadBase, $saldo - $cantidadBase, $costo, $origen,
        );

        return $costo;
    }

    public function increase(ProductoPresentacion $precio, int $sucursalId, float $cantidad, OrigenMovimiento $origen, float $costo = 0): void
    {
        $this->aumentarSaldo((int) ($precio->id_producto_stock ?? $precio->id_producto), $sucursalId, $this->toBaseQuantity($precio, $cantidad), $origen, $costo);
    }

    public function reponerConsumo(int $producto, int $sucursal, float $cantidadBase, float $costo, OrigenMovimiento $origen): void
    {
        $this->aumentarSaldo($producto, $sucursal, $cantidadBase, $origen, $costo);
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
