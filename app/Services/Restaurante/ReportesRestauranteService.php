<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Services\Inventario\OrigenMovimiento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportesRestauranteService
{
    public function consultar(array $sucursales, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $postgres = DB::connection()->getDriverName() === 'pgsql';
        $formato = $postgres ? 'Y-m-d H:i:sP' : 'Y-m-d H:i:s';
        $inicio = $desde->utc()->format($formato);
        $fin = $hasta->addDay()->utc()->format($formato);
        $ventas = $this->rango(DB::table('operaciones.ventas as v')->whereNotNull('v.id_pedido'), 'v', 'fecha_emision', $sucursales, $inicio, $fin);
        $lineas = (clone $ventas)
            ->join('operaciones.detalle_ventas as d', 'd.id_venta', '=', 'v.id_venta')
            ->join('restaurante.pedido_detalles as pd', 'pd.id_detalle_pedido', '=', 'd.id_detalle_pedido')
            ->join('catalogos.productos as p', 'p.id_producto', '=', 'pd.id_producto');
        $dia = $postgres ? "CAST(v.fecha_emision AT TIME ZONE 'America/Lima' AS DATE)" : "DATE(v.fecha_emision, '-05:00')";
        $totales = (clone $ventas)->selectRaw('COUNT(*) as cobros, COALESCE(SUM(v.total_venta), 0) as total, COALESCE(SUM(v.op_gravadas + v.op_exoneradas), 0) as base')->first();
        $hora = $postgres ? "CAST(EXTRACT(HOUR FROM v.fecha_emision AT TIME ZONE 'America/Lima') AS INTEGER)" : "CAST(strftime('%H', v.fecha_emision, '-05:00') AS INTEGER)";

        // Una cuenta puede cobrarse en varias partes: los pedidos y comensales se cuentan una vez.
        $atendidos = DB::table('restaurante.pedidos as p')
            ->whereIn('p.id_pedido', (clone $ventas)->select('v.id_pedido'))
            ->selectRaw('COUNT(*) as pedidos, COALESCE(SUM(CASE WHEN p.tipo = ? AND p.comensales > 0 THEN p.comensales END), 0) as comensales', [Pedido::MESA])
            ->first();
        // Gasto por comensal: solo cuentas que registraron comensales (las mesas).
        $conComensales = (clone $ventas)->join('restaurante.pedidos as pc', 'pc.id_pedido', '=', 'v.id_pedido')
            ->where('pc.tipo', Pedido::MESA)->where('pc.comensales', '>', 0)->sum('v.total_venta');
        $tipos = (clone $ventas)->join('restaurante.pedidos as pt', 'pt.id_pedido', '=', 'v.id_pedido')
            ->groupBy('pt.tipo')->orderByDesc('total')->orderBy('pt.tipo')
            ->selectRaw('pt.tipo, COUNT(DISTINCT v.id_pedido) as pedidos, SUM(v.total_venta) as total')->get()
            ->map(fn (object $fila): array => ['tipo' => $fila->tipo, 'pedidos' => (int) $fila->pedidos, 'total' => (float) $fila->total])->all();

        // Período anterior de la misma duración, para comparar.
        $dias = $desde->diffInDays($hasta) + 1;
        $anteriorDesde = $desde->subDays($dias);
        $previas = $this->rango(DB::table('operaciones.ventas as v')->whereNotNull('v.id_pedido'), 'v', 'fecha_emision', $sucursales,
            $anteriorDesde->utc()->format($formato), $desde->utc()->format($formato))
            ->selectRaw('COUNT(*) as cobros, COALESCE(SUM(v.total_venta), 0) as total')->first();

        $costosReceta = DB::table('restaurante.consumos_insumos')->whereIn('id_sucursal', $sucursales)
            ->where('estado', 'CONSUMIDO')->groupBy('id_detalle_pedido')
            ->selectRaw('id_detalle_pedido, SUM(cantidad_base * costo_unitario) as costo');
        $recetas = (clone $lineas)->leftJoinSub($costosReceta, 'rec', 'rec.id_detalle_pedido', '=', 'pd.id_detalle_pedido')
            ->selectRaw("COALESCE(SUM(rec.costo * 1.0 * d.cantidad / NULLIF(pd.cantidad, 0)), 0) as costo,
                COUNT(CASE WHEN rec.id_detalle_pedido IS NULL AND COALESCE(pd.tipo_producto, p.tipo_producto) = 'PREPARADO' THEN 1 END) as sin_costo")->first();
        $directos = DB::table('inventario.kardex as k')->whereIn('k.id_sucursal', $sucursales)
            ->where('k.tipo_operacion', OrigenMovimiento::VENTA)
            ->whereIn('k.id_referencia', (clone $ventas)->select('v.id_venta'))
            ->selectRaw('COALESCE(SUM(k.cantidad_salida * k.precio_costo), 0) as costo')->value('costo');
        $costo = round((float) $recetas->costo + (float) $directos, 2);

        $porSucursal = (clone $ventas)->join('configuracion.sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')
            ->groupBy('s.id_sucursal', 's.nombre')->orderBy('s.nombre')
            ->selectRaw('s.nombre, COUNT(*) as cobros, SUM(v.total_venta) as total')->get();

        $caja = (clone $ventas)->join('operaciones.turnos_caja as t', 't.id_turno', '=', 'v.id_turno')
            ->whereColumn('v.id_sucursal', 't.id_sucursal')
            ->join('seguridad.usuarios as u', 'u.id_usuario', '=', 't.id_usuario')
            ->join('configuracion.sucursales as s', 's.id_sucursal', '=', 't.id_sucursal')
            ->groupBy('t.id_turno', 'u.nombres', 's.nombre', 't.estado', 't.monto_cierre_declarado')
            ->orderByDesc('t.id_turno')->limit(30)
            ->selectRaw('t.id_turno, u.nombres, s.nombre as sucursal, t.estado, t.monto_cierre_declarado, COUNT(*) as cobros, SUM(v.total_venta) as total')->get();

        $insumos = $this->rango(DB::table('inventario.kardex as k'), 'k', 'ultima_actualizacion', $sucursales, $inicio, $fin)
            ->whereIn('k.tipo_operacion', [OrigenMovimiento::CONSUMO_RECETA, OrigenMovimiento::REVERSION_RECETA, OrigenMovimiento::MERMA])
            ->join('catalogos.productos as p', 'p.id_producto', '=', 'k.id_producto')
            ->leftJoin('catalogos.unidades_medida as u', 'u.id_unidad', '=', 'p.id_unidad_base')
            ->groupBy('p.id_producto', 'p.nombre', 'u.nombre')->orderBy('p.nombre')
            ->selectRaw("p.nombre, u.nombre as unidad, SUM(CASE WHEN k.tipo_operacion = 'CONSUMO_RECETA' THEN k.cantidad_salida ELSE 0 END) as consumo,
                SUM(k.cantidad_entrada) as devoluciones, SUM(CASE WHEN k.tipo_operacion = 'MERMA' THEN k.cantidad_salida ELSE 0 END) as merma_directa")->get();
        $mermaCocina = $this->rango(DB::table('restaurante.consumos_insumos as i')->where('i.estado', 'MERMA'), 'i', 'resuelto_en', $sucursales, $inicio, $fin)
            ->join('catalogos.productos as p', 'p.id_producto', '=', 'i.id_producto')
            ->leftJoin('catalogos.unidades_medida as u', 'u.id_unidad', '=', 'p.id_unidad_base')
            ->groupBy('p.id_producto', 'p.nombre', 'u.nombre')->orderBy('p.nombre')
            ->selectRaw('p.nombre, u.nombre as unidad, SUM(i.cantidad_base) as cantidad, SUM(i.cantidad_base * i.costo_unitario) as costo')->get();
        $anulados = $this->rango(DB::table('restaurante.pedidos as p')->where('p.estado', 'ANULADO'), 'p', 'cerrado_en', $sucursales, $inicio, $fin)
            ->join('configuracion.sucursales as s', 's.id_sucursal', '=', 'p.id_sucursal');
        $preparacion = $this->rango(DB::table('restaurante.comandas as c')->whereNotNull('c.lista_en'), 'c', 'enviada_en', $sucursales, $inicio, $fin)
            ->leftJoin('restaurante.estaciones as e', 'e.id_estacion', '=', 'c.id_estacion')
            ->join('configuracion.sucursales as s', 's.id_sucursal', '=', 'c.id_sucursal');
        $segundos = $postgres ? 'EXTRACT(EPOCH FROM (c.lista_en - COALESCE(c.iniciada_en, c.enviada_en)))'
            : "(strftime('%s', c.lista_en) - strftime('%s', COALESCE(c.iniciada_en, c.enviada_en)))";

        return [
            'resumen' => [
                'total' => (float) $totales->total, 'cobros' => (int) $totales->cobros,
                'costo_registrado' => $costo, 'lineas_sin_costo' => (int) $recetas->sin_costo,
                'margen' => $recetas->sin_costo ? null : round((float) $totales->base - $costo, 2),
                'pedidos_anulados' => (clone $anulados)->count(),
                'ticket_promedio' => $this->promedio((float) $totales->total, (int) $totales->cobros),
                'pedidos' => (int) $atendidos->pedidos,
                'comensales' => (int) $atendidos->comensales,
                'gasto_por_comensal' => $this->promedio((float) $conComensales, (int) $atendidos->comensales),
                'minutos_cocina' => $this->minutosCocina(clone $preparacion, $segundos),
            ],
            'anterior' => [
                'desde' => $anteriorDesde->toDateString(), 'hasta' => $desde->subDay()->toDateString(),
                'total' => (float) $previas->total, 'cobros' => (int) $previas->cobros,
                'ticket_promedio' => $this->promedio((float) $previas->total, (int) $previas->cobros),
            ],
            'tipos' => $tipos,
            'horas' => (clone $ventas)->groupByRaw($hora)->orderByRaw($hora)
                ->selectRaw("{$hora} as hora, COUNT(*) as cobros, SUM(v.total_venta) as total")->get()
                ->map(fn (object $fila): array => ['hora' => (int) $fila->hora, 'cobros' => (int) $fila->cobros, 'total' => (float) $fila->total])->all(),
            'dias' => (clone $ventas)->groupByRaw($dia)->orderBy('dia')->selectRaw("{$dia} as dia, COUNT(*) as cobros, SUM(v.total_venta) as total")->get(),
            'sucursales' => $porSucursal,
            'platos' => (clone $lineas)->whereRaw("COALESCE(pd.tipo_producto, p.tipo_producto) = 'PREPARADO'")
                ->groupBy('pd.id_producto', 'pd.nombre_producto')->limit(20)->orderByDesc('cantidad')->orderBy('pd.nombre_producto')
                ->selectRaw('pd.nombre_producto as nombre, SUM(d.cantidad) as cantidad, SUM(d.subtotal) as total')->get(),
            'categorias' => (clone $lineas)->leftJoin('catalogos.categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
                ->groupBy('c.id_categoria', 'c.nombre')->orderByDesc('total')
                ->selectRaw("COALESCE(c.nombre, 'Sin categoría') as nombre, SUM(d.subtotal) as total")->get(),
            'insumos' => $insumos,
            'merma_cocina' => $mermaCocina,
            'caja' => $caja,
            'anulados' => $anulados->orderByDesc('p.cerrado_en')->limit(30)
                ->get(['p.numero', 'p.tipo', 'p.motivo_anulacion', 's.nombre as sucursal', 'p.cerrado_en'])
                ->map(function (object $pedido): object {
                    $pedido->cerrado_en = CarbonImmutable::parse($pedido->cerrado_en, 'UTC')->toIso8601String();
                    return $pedido;
                }),
            'cocina' => $preparacion->groupBy('s.id_sucursal', 's.nombre', 'e.id_estacion', 'e.nombre')->orderBy('s.nombre')->orderBy('e.nombre')
                ->selectRaw("s.nombre as sucursal, COALESCE(e.nombre, 'Cocina general') as nombre, COUNT(*) as comandas, AVG({$segundos}) as segundos")->get(),
        ];
    }

    private function promedio(float $total, int $cantidad): ?float
    {
        return $cantidad > 0 ? round($total / $cantidad, 2) : null;
    }

    /** Minutos promedio desde que la comanda se empieza (o llega) hasta que queda lista. */
    private function minutosCocina(Builder $preparacion, string $segundos): ?float
    {
        $promedio = $preparacion->selectRaw("AVG({$segundos}) as segundos")->value('segundos');

        return $promedio === null ? null : round((float) $promedio / 60, 1);
    }

    private function rango(Builder $consulta, string $alias, string $fecha, array $sucursales, string $inicio, string $fin): Builder
    {
        return $consulta->whereIn("{$alias}.id_sucursal", $sucursales)->where("{$alias}.{$fecha}", '>=', $inicio)->where("{$alias}.{$fecha}", '<', $fin);
    }
}
