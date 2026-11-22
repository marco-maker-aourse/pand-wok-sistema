<?php

namespace App\Services\Caja;

use App\Models\MetodoPago;
use App\Models\MovimientoCaja;
use App\Models\TerminalPos;
use App\Models\TurnoCaja;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza las invariantes de una gaveta física.
 *
 * Cada operación crítica bloquea el turno. Así una venta, movimiento o cierre
 * no puede confirmarse sobre un turno que cambió de estado en paralelo.
 */
class GestionarTurnoCajaService
{
    public function abrir(int $usuarioId, int $sucursalId, float $montoApertura, ?int $terminalId = null): TurnoCaja
    {
        return DB::transaction(function () use ($usuarioId, $sucursalId, $montoApertura, $terminalId): TurnoCaja {
            // Serializa aperturas concurrentes del mismo usuario sin bloquear la sucursal completa.
            DB::table('seguridad.usuarios')->where('id_usuario', $usuarioId)->lockForUpdate()->firstOrFail();

            $existeAbierto = TurnoCaja::query()
                ->where('id_usuario', $usuarioId)
                ->where('id_sucursal', $sucursalId)
                ->where('estado', 'ABIERTO')
                ->exists();

            if ($existeAbierto) {
                throw new DomainException('Ya existe un turno de caja abierto para este usuario.');
            }

            if ($terminalId !== null) {
                TerminalPos::query()
                    ->whereKey($terminalId)
                    ->where('id_sucursal', $sucursalId)
                    ->where('activo', true)
                    ->whereHas('impresora', fn ($query) => $query->where('activo', true))
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            return TurnoCaja::create([
                'id_usuario' => $usuarioId,
                'id_sucursal' => $sucursalId,
                'id_terminal' => $terminalId,
                'monto_apertura' => round($montoApertura, 2),
                'fecha_apertura' => now(),
                'estado' => 'ABIERTO',
            ]);
        }, attempts: 3);
    }

    /**
     * Recupera un turno histórico abierto que fue creado antes de exigir terminal.
     * La terminal física permanece estable; cada turno diario solo conserva su referencia.
     */
    public function asignarTerminal(int $turnoId, int $usuarioId, int $sucursalId, int $terminalId): TurnoCaja
    {
        return DB::transaction(function () use ($turnoId, $usuarioId, $sucursalId, $terminalId): TurnoCaja {
            $turno = $this->turnoAbiertoBloqueado($turnoId, $usuarioId, $sucursalId);

            if ($turno->id_terminal !== null) {
                throw new DomainException('El turno ya tiene una terminal asignada y no puede cambiarse durante la operación.');
            }

            TerminalPos::query()
                ->whereKey($terminalId)
                ->where('id_sucursal', $sucursalId)
                ->where('activo', true)
                ->whereHas('impresora', fn ($query) => $query->where('activo', true))
                ->lockForUpdate()
                ->firstOrFail();

            $turno->update(['id_terminal' => $terminalId]);

            return $turno->fresh();
        }, attempts: 3);
    }

    public function registrarMovimiento(int $turnoId, int $usuarioId, int $sucursalId, string $tipo, float $monto, string $motivo): MovimientoCaja
    {
        return DB::transaction(function () use ($turnoId, $usuarioId, $sucursalId, $tipo, $monto, $motivo): MovimientoCaja {
            $turno = $this->turnoAbiertoBloqueado($turnoId, $usuarioId, $sucursalId);

            if ($tipo === 'EGRESO' && $monto > $this->resumen($turno)['esperado_gaveta']) {
                throw new DomainException('El egreso no puede superar el efectivo disponible en gaveta.');
            }

            return MovimientoCaja::create([
                'id_turno' => $turno->id_turno,
                'id_usuario' => $usuarioId,
                'tipo_movimiento' => $tipo,
                'monto' => round($monto, 2),
                'motivo' => trim($motivo),
                'fecha_movimiento' => now(),
            ]);
        }, attempts: 3);
    }

    public function cerrar(int $turnoId, int $usuarioId, int $sucursalId, float $montoDeclarado): float
    {
        return DB::transaction(function () use ($turnoId, $usuarioId, $sucursalId, $montoDeclarado): float {
            $turno = $this->turnoAbiertoBloqueado($turnoId, $usuarioId, $sucursalId);
            $resumen = $this->resumen($turno);
            $diferencia = round($montoDeclarado - $resumen['esperado_gaveta'], 2);

            $turno->update([
                'monto_cierre_declarado' => round($montoDeclarado, 2),
                'fecha_cierre' => now(),
                'estado' => 'CERRADO',
            ]);

            return $diferencia;
        }, attempts: 3);
    }

    /** Resumen de caja: efectivo físico separado de medios digitales. */
    public function resumen(TurnoCaja $turno): array
    {
        $pagos = DB::table('operaciones.pagos_ventas as pago')
            ->join('operaciones.ventas as venta', 'pago.id_venta', '=', 'venta.id_venta')
            ->join('catalogos.metodos_pago as metodo', 'pago.id_metodo', '=', 'metodo.id_metodo')
            ->where('venta.id_turno', $turno->id_turno)
            ->where('pago.estado_pago', 'COMPLETADO')
            ->select('metodo.nombre', 'metodo.tipo')
            ->selectRaw('SUM(pago.monto) as monto_bruto')
            // Neto = bruto menos la comisión de la pasarela; no se guarda porque se deriva.
            ->selectRaw('SUM(pago.monto - pago.comision_pasarela) as monto_neto')
            ->groupBy('metodo.nombre', 'metodo.tipo')
            ->get();

        $porMetodo = $pagos->mapWithKeys(fn ($pago): array => [$pago->nombre => [
            'tipo' => $pago->tipo,
            'bruto' => round((float) $pago->monto_bruto, 2),
            'neto' => round((float) $pago->monto_neto, 2),
        ]]);
        $efectivo = (float) $pagos->where('tipo', MetodoPago::EFECTIVO)->sum(fn ($pago): float => (float) $pago->monto_bruto);
        $digital = (float) $pagos->where('tipo', '!=', MetodoPago::EFECTIVO)->sum(fn ($pago): float => (float) $pago->monto_neto);
        $movimientos = $this->movimientosTotales($turno->id_turno);
        $esperado = round((float) $turno->monto_apertura + $efectivo + $movimientos['ingresos'] - $movimientos['egresos'], 2);

        return [
            'monto_apertura' => (float) $turno->monto_apertura,
            'ventas_efectivo' => round($efectivo, 2),
            'ventas_digitales_netas' => round($digital, 2),
            'ventas_por_metodo' => $porMetodo,
            'ingresos_caja' => $movimientos['ingresos'],
            'egresos_caja' => $movimientos['egresos'],
            'esperado_gaveta' => $esperado,
        ];
    }

    /**
     * Historial de turnos con la diferencia entre lo declarado y lo esperado en gaveta.
     * Misma fórmula que resumen(), calculada en lote para no consultar por turno.
     */
    public function conDiferenciaDeCuadre(Collection $turnos): Collection
    {
        if ($turnos->isEmpty()) {
            return $turnos;
        }

        $turnoIds = $turnos->pluck('id_turno');
        // Un solo GROUP BY para todos los turnos elimina el N+1 del historial de caja.
        $efectivoPorTurno = DB::table('operaciones.pagos_ventas as pago')
            ->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'pago.id_venta')
            ->join('catalogos.metodos_pago as metodo', 'metodo.id_metodo', '=', 'pago.id_metodo')
            ->whereIn('venta.id_turno', $turnoIds)
            ->where('pago.estado_pago', 'COMPLETADO')
            ->groupBy('venta.id_turno')
            ->select('venta.id_turno')
            ->selectRaw('COALESCE(SUM(CASE WHEN metodo.tipo = ? THEN pago.monto ELSE 0 END), 0) as monto_efectivo', [MetodoPago::EFECTIVO])
            ->pluck('monto_efectivo', 'venta.id_turno');

        $movimientosPorTurno = DB::table('operaciones.movimientos_caja')
            ->whereIn('id_turno', $turnoIds)
            ->groupBy('id_turno')
            ->select('id_turno')
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_movimiento = 'INGRESO' THEN monto ELSE 0 END), 0) as ingresos")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_movimiento = 'EGRESO' THEN monto ELSE 0 END), 0) as egresos")
            ->get()
            ->keyBy('id_turno');

        return $turnos->map(function (TurnoCaja $turno) use ($efectivoPorTurno, $movimientosPorTurno): array {
            $movimientos = $movimientosPorTurno->get($turno->id_turno);
            $esperado = (float) $turno->monto_apertura
                + (float) ($efectivoPorTurno[$turno->id_turno] ?? 0)
                + (float) ($movimientos?->ingresos ?? 0)
                - (float) ($movimientos?->egresos ?? 0);

            return [
                ...$turno->toArray(),
                'diferencia_cuadre' => $turno->monto_cierre_declarado === null
                    ? null
                    : round((float) $turno->monto_cierre_declarado - $esperado, 2),
            ];
        });
    }

    public function turnoAbiertoBloqueado(int $turnoId, int $usuarioId, int $sucursalId): TurnoCaja
    {
        $turno = TurnoCaja::query()
            ->whereKey($turnoId)
            ->where('id_usuario', $usuarioId)
            ->where('id_sucursal', $sucursalId)
            ->where('estado', 'ABIERTO')
            ->lockForUpdate()
            ->first();

        if (! $turno) {
            throw new DomainException('El turno no está abierto o no pertenece al usuario actual.');
        }

        return $turno;
    }

    private function movimientosTotales(int $turnoId): array
    {
        $movimientos = DB::table('operaciones.movimientos_caja')
            ->where('id_turno', $turnoId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_movimiento = 'INGRESO' THEN monto ELSE 0 END), 0) as ingresos")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_movimiento = 'EGRESO' THEN monto ELSE 0 END), 0) as egresos")
            ->first();

        return [
            'ingresos' => round((float) ($movimientos->ingresos ?? 0), 2),
            'egresos' => round((float) ($movimientos->egresos ?? 0), 2),
        ];
    }
}
