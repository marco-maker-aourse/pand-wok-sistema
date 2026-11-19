<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Pedido;
use DomainException;

/**
 * Lecturas con bloqueo de fila compartidas por los servicios del salón.
 *
 * Siempre filtran por sucursal de forma explícita: SucursalScope no actúa en consola
 * ni en colas, y un id de otra sede debe comportarse como inexistente.
 * Debe llamarse dentro de una transacción.
 */
class BloqueoSalonService
{
    public function pedidoAbierto(int $pedidoId, int $sucursalId): Pedido
    {
        $pedido = Pedido::query()
            ->where('id_sucursal', $sucursalId)
            ->lockForUpdate()
            ->find($pedidoId);

        if (! $pedido) {
            throw new DomainException('El pedido no existe en esta sucursal.');
        }
        if (! $pedido->estaAbierto()) {
            throw new DomainException("El pedido #{$pedido->numero} ya no está abierto.");
        }

        return $pedido;
    }

    /**
     * Bloquea dos pedidos en orden de id para que dos operaciones cruzadas
     * (A→B y B→A al mismo tiempo) no se bloqueen mutuamente.
     *
     * @return array{0: Pedido, 1: Pedido}
     */
    public function dosPedidosAbiertos(int $origenId, int $destinoId, int $sucursalId): array
    {
        if ($origenId === $destinoId) {
            throw new DomainException('El pedido de origen y el de destino deben ser distintos.');
        }

        $bloqueados = [];
        foreach ([min($origenId, $destinoId), max($origenId, $destinoId)] as $id) {
            $bloqueados[$id] = $this->pedidoAbierto($id, $sucursalId);
        }

        return [$bloqueados[$origenId], $bloqueados[$destinoId]];
    }

    public function mesa(int $mesaId, int $sucursalId): Mesa
    {
        $mesa = Mesa::query()
            ->where('id_sucursal', $sucursalId)
            ->lockForUpdate()
            ->find($mesaId);

        if (! $mesa) {
            throw new DomainException('La mesa no existe en esta sucursal.');
        }

        return $mesa;
    }

    /** Valida que la mesa pueda recibir una cuenta nueva y la marca como ocupada. */
    public function ocuparMesa(Mesa $mesa): void
    {
        if (! $mesa->admiteNuevaCuenta()) {
            throw new DomainException("La mesa {$mesa->codigo} no está disponible.");
        }

        $tieneCuenta = Pedido::query()
            ->where('id_mesa', $mesa->id_mesa)
            ->where('estado', Pedido::ABIERTO)
            ->exists();
        if ($tieneCuenta) {
            throw new DomainException("La mesa {$mesa->codigo} ya tiene una cuenta abierta.");
        }

        $mesa->update(['estado' => Mesa::OCUPADA]);
    }

    /** Libera la mesa de un pedido que se cierra, anula, une o cambia de mesa. */
    public function liberarMesa(?int $mesaId, int $sucursalId): void
    {
        if (! $mesaId) {
            return;
        }

        $mesa = $this->mesa($mesaId, $sucursalId);
        if ($mesa->estado === Mesa::OCUPADA) {
            $mesa->update(['estado' => Mesa::DISPONIBLE]);
        }
    }
}
