<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Rango elegido por el operador. Las consultas siguen agregando en PostgreSQL,
     * evitando cargar ventas completas para filtrar o calcular métricas en PHP.
     */
    public function forDateRange(int $branchId, CarbonInterface $desde, CarbonInterface $hasta, ?int $userId = null): array
    {
        if (! $branchId) return $this->emptyMetrics();

        $inicio = $desde->copy()->startOfDay()->utc();
        $fin = $hasta->copy()->addDay()->startOfDay()->utc();
        $summary = $this->summaryByRange($branchId, $inicio, $fin);

        return [
            'periodo' => 'personalizado',
            'summary' => $summary,
            'hourlySales' => $this->dailySalesByRange($branchId, $inicio, $fin),
            'esDiario' => true,
            'paymentMethods' => $this->paymentMethodsByRange($branchId, $inicio, $fin),
            'recentSales' => $this->recentSalesByRange($branchId, $inicio, $fin),
            'stockAlerts' => $this->stockAlerts($branchId),
            'cashShift' => $this->cashShift($branchId, $userId),
            'insights' => $this->insights($branchId, $summary, $inicio, $fin),
            'generatedAt' => now()->toIso8601String(),
        ];
    }
    public function forToday(?int $branchId, ?int $userId = null): array
    {
        return $this->forPeriod($branchId, 'hoy', $userId);
    }

    public function forPeriod(?int $branchId, string $period = 'hoy', ?int $userId = null): array
    {
        if (! $branchId) {
            return $this->emptyMetrics();
        }

        $validPeriods = ['hoy', 'semana', '2_semanas', 'mes', '3_meses'];
        if (! in_array($period, $validPeriods, true)) {
            $period = 'hoy';
        }

        $summary = $this->summary($branchId, $period);

        return [
            'periodo'        => $period,
            'summary'        => $summary,
            'hourlySales'    => $period === 'hoy' ? $this->hourlySales($branchId) : $this->dailySales($branchId, $period),
            'esDiario'       => $period !== 'hoy',
            'paymentMethods' => $this->paymentMethods($branchId, $period),
            'recentSales'    => $this->recentSales($branchId, $period),
            'stockAlerts'    => $this->stockAlerts($branchId),
            'cashShift'      => $this->cashShift($branchId, $userId),
            'insights'       => $this->insights($branchId, $summary, Carbon::now('America/Lima')->subDays(match ($period) { 'semana' => 6, '2_semanas' => 13, 'mes' => 29, '3_meses' => 89, default => 0 })->startOfDay()->utc(), Carbon::now('America/Lima')->addDay()->startOfDay()->utc()),
            'generatedAt'    => now()->toIso8601String(),
        ];
    }

    private function getIntervalSql(string $period): string
    {
        return match ($period) {
            'semana'    => "INTERVAL '6 days'",
            '2_semanas' => "INTERVAL '13 days'",
            'mes'       => "INTERVAL '29 days'",
            '3_meses'   => "INTERVAL '89 days'",
            default     => "INTERVAL '0 days'",
        };
    }

    private function summary(int $branchId, string $period): array
    {
        $interval = $this->getIntervalSql($period);

        $row = DB::selectOne("
            WITH period_sales AS (
                SELECT total_venta, id_cliente, estado_sunat FROM operaciones.ventas
                WHERE id_sucursal = ?
                  AND fecha_emision >= (((now() AT TIME ZONE 'America/Lima')::date) - {$interval})::timestamp AT TIME ZONE 'America/Lima'
                  AND fecha_emision < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
            ), latest_stock AS (
                SELECT DISTINCT ON (id_producto) id_producto, stock_actual
                FROM inventario.kardex WHERE id_sucursal = ?
                ORDER BY id_producto, id_kardex DESC
            )
            SELECT COALESCE((SELECT SUM(total_venta) FROM period_sales), 0) AS sales_total,
                   (SELECT COUNT(*) FROM period_sales) AS sales_count,
                   COALESCE((SELECT AVG(total_venta) FROM period_sales), 0) AS average_ticket,
                   (SELECT COUNT(DISTINCT id_cliente) FROM period_sales) AS unique_customers,
                   (SELECT COUNT(*) FROM period_sales WHERE estado_sunat IN ('PENDIENTE', 'ERROR')) AS pending_sunat_count,
                   COALESCE((SELECT SUM(total_ingreso) FROM operaciones.ingresos WHERE id_sucursal = ?
                     AND fecha_emision >= ((now() AT TIME ZONE 'America/Lima')::date - {$interval})
                     AND fecha_emision < ((now() AT TIME ZONE 'America/Lima')::date + INTERVAL '1 day')
                   ), 0) AS purchases_total,
                   (SELECT COUNT(*) FROM operaciones.ingresos WHERE id_sucursal = ?
                     AND fecha_emision >= ((now() AT TIME ZONE 'America/Lima')::date - {$interval})
                     AND fecha_emision < ((now() AT TIME ZONE 'America/Lima')::date + INTERVAL '1 day')
                   ) AS purchases_count,
                   (SELECT COUNT(*) FROM restaurante.mermas WHERE id_sucursal = ?
                     AND registrada_en >= (((now() AT TIME ZONE 'America/Lima')::date) - {$interval})::timestamp AT TIME ZONE 'America/Lima'
                     AND registrada_en < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
                   ) AS adjustments_count,
                   (SELECT COUNT(*) FROM catalogos.productos products LEFT JOIN latest_stock stock ON stock.id_producto = products.id_producto WHERE products.estado = TRUE AND products.es_servicio = FALSE AND COALESCE(stock.stock_actual, 0) <= 0) AS zero_stock_count
        ", [$branchId, $branchId, $branchId, $branchId, $branchId]);

        return [
            'salesTotal'       => (float) ($row->sales_total ?? 0),
            'salesCount'       => (int) ($row->sales_count ?? 0),
            'averageTicket'    => (float) ($row->average_ticket ?? 0),
            'uniqueCustomers'  => (int) ($row->unique_customers ?? 0),
            'pendingSunatCount'=> (int) ($row->pending_sunat_count ?? 0),
            'purchasesTotal'   => (float) ($row->purchases_total ?? 0),
            'purchasesCount'   => (int) ($row->purchases_count ?? 0),
            'adjustmentsCount' => (int) ($row->adjustments_count ?? 0),
            'zeroStockCount'   => (int) ($row->zero_stock_count ?? 0),
        ];
    }

    private function hourlySales(int $branchId): array
    {
        $rows = DB::select("
            -- Las ventas se almacenan en UTC; el tablero siempre opera con hora comercial peruana.
            SELECT EXTRACT(HOUR FROM fecha_emision AT TIME ZONE 'America/Lima')::int AS hour, SUM(total_venta) AS total
            FROM operaciones.ventas 
            WHERE id_sucursal = ? 
              AND fecha_emision >= ((now() AT TIME ZONE 'America/Lima')::date)::timestamp AT TIME ZONE 'America/Lima'
              AND fecha_emision < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
            GROUP BY EXTRACT(HOUR FROM fecha_emision AT TIME ZONE 'America/Lima')
            ORDER BY hour
        ", [$branchId]);

        $totals = collect($rows)->mapWithKeys(fn (object $row) => [(int) $row->hour => (float) $row->total]);

        return collect(range(8, 20))->map(fn (int $hour) => [
            'label' => sprintf('%02d:00', $hour),
            'total' => $totals->get($hour, 0.0),
        ])->all();
    }

    private function dailySales(int $branchId, string $period): array
    {
        $interval = $this->getIntervalSql($period);

        $rows = DB::select("
            SELECT 
                (fecha_emision AT TIME ZONE 'America/Lima')::date AS dia,
                to_char(fecha_emision AT TIME ZONE 'America/Lima', 'DD/MM') AS label,
                COALESCE(SUM(total_venta), 0) AS total
            FROM operaciones.ventas
            WHERE id_sucursal = ?
              AND fecha_emision >= (((now() AT TIME ZONE 'America/Lima')::date) - {$interval})::timestamp AT TIME ZONE 'America/Lima'
              AND fecha_emision < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
            GROUP BY dia, label
            ORDER BY dia ASC
        ", [$branchId]);

        $diasMap = collect($rows)->keyBy('dia');

        $diasCantidad = match ($period) {
            'semana'    => 7,
            '2_semanas' => 14,
            'mes'       => 30,
            '3_meses'   => 90,
            default     => 7,
        };

        $resultado = [];
        $fechaCursor = Carbon::now('America/Lima')->subDays($diasCantidad - 1)->startOfDay();
        $hoy = Carbon::now('America/Lima')->endOfDay();

        while ($fechaCursor->lte($hoy)) {
            $diaStr = $fechaCursor->format('Y-m-d');
            $label = $fechaCursor->format('d/m');
            $total = isset($diasMap[$diaStr]) ? (float) $diasMap[$diaStr]->total : 0.0;

            $resultado[] = [
                'label' => $label,
                'total' => $total,
            ];

            $fechaCursor->addDay();
        }

        return $resultado;
    }

    private function paymentMethods(int $branchId, string $period): array
    {
        $interval = $this->getIntervalSql($period);

        $rows = DB::select("
            SELECT method.nombre, SUM(payment.monto) AS total 
            FROM operaciones.pagos_ventas payment 
            INNER JOIN operaciones.ventas sale ON sale.id_venta = payment.id_venta 
            INNER JOIN catalogos.metodos_pago method ON method.id_metodo = payment.id_metodo 
            WHERE sale.id_sucursal = ? 
              AND sale.fecha_emision >= (((now() AT TIME ZONE 'America/Lima')::date) - {$interval})::timestamp AT TIME ZONE 'America/Lima'
              AND sale.fecha_emision < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
              AND payment.estado_pago = 'COMPLETADO' 
            GROUP BY method.id_metodo, method.nombre 
            ORDER BY total DESC LIMIT 4
        ", [$branchId]);

        return collect($rows)->map(fn (object $row) => ['name' => $row->nombre, 'total' => (float) $row->total])->all();
    }

    private function recentSales(int $branchId, string $period): array
    {
        $interval = $this->getIntervalSql($period);

        $rows = DB::select("
            SELECT sale.id_venta, sale.serie, sale.correlativo, sale.total_venta, sale.fecha_emision, 
                   client.nombre_razon_social AS customer_name, receipt.descripcion AS receipt_name 
            FROM operaciones.ventas sale 
            INNER JOIN catalogos.clientes client ON client.id_cliente = sale.id_cliente 
            INNER JOIN catalogos.tipos_comprobante receipt ON receipt.id_tipo_comprobante = sale.id_tipo_comprobante 
            WHERE sale.id_sucursal = ? 
              AND sale.fecha_emision >= (((now() AT TIME ZONE 'America/Lima')::date) - {$interval})::timestamp AT TIME ZONE 'America/Lima'
              AND sale.fecha_emision < ((((now() AT TIME ZONE 'America/Lima')::date) + INTERVAL '1 day')::timestamp AT TIME ZONE 'America/Lima')
            ORDER BY sale.fecha_emision DESC LIMIT 6
        ", [$branchId]);

        return collect($rows)->map(fn (object $row) => [
            'id'       => (int) $row->id_venta,
            'document' => sprintf('%s-%s', $row->serie, str_pad((string) $row->correlativo, 6, '0', STR_PAD_LEFT)),
            'customer' => $row->customer_name,
            'receipt'  => $row->receipt_name,
            'total'    => (float) $row->total_venta,
            'issuedAt' => $row->fecha_emision,
        ])->all();
    }

    private function stockAlerts(int $branchId): array
    {
        $rows = DB::select("
            WITH latest_stock AS (
                SELECT DISTINCT ON (id_producto) id_producto, stock_actual 
                FROM inventario.kardex WHERE id_sucursal = ? 
                ORDER BY id_producto, id_kardex DESC
            ) 
            SELECT products.id_producto, products.nombre, COALESCE(stock.stock_actual, 0) AS stock 
            FROM catalogos.productos products 
            LEFT JOIN latest_stock stock ON stock.id_producto = products.id_producto 
            WHERE products.estado = TRUE AND products.es_servicio = FALSE AND COALESCE(stock.stock_actual, 0) <= 0 
            ORDER BY products.nombre LIMIT 5
        ", [$branchId]);

        return collect($rows)->map(fn (object $row) => ['id' => (int) $row->id_producto, 'name' => $row->nombre, 'stock' => (float) $row->stock])->all();
    }

    private function cashShift(int $branchId, ?int $userId): ?array
    {
        if (! $userId) return null;

        $row = DB::selectOne("
            SELECT id_turno, monto_apertura, fecha_apertura 
            FROM operaciones.turnos_caja 
            WHERE id_sucursal = ? AND id_usuario = ? AND estado = 'ABIERTO' 
            ORDER BY fecha_apertura DESC LIMIT 1
        ", [$branchId, $userId]);

        return $row ? [
            'id'             => (int) $row->id_turno,
            'id_turno'       => (int) $row->id_turno,
            'openingAmount'  => (float) $row->monto_apertura,
            'monto_apertura' => (float) $row->monto_apertura,
            'openedAt'       => $row->fecha_apertura,
            'fecha_apertura' => $row->fecha_apertura,
            'usuario'        => ['nombres' => 'Usuario Activo']
        ] : null;
    }

    private function summaryByRange(int $branchId, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        $row = DB::selectOne("\n            WITH period_sales AS (\n                SELECT total_venta, id_cliente, estado_sunat FROM operaciones.ventas\n                WHERE id_sucursal = ? AND fecha_emision >= ? AND fecha_emision < ?\n            ), latest_stock AS (\n                SELECT DISTINCT ON (id_producto) id_producto, stock_actual\n                FROM inventario.kardex WHERE id_sucursal = ? ORDER BY id_producto, id_kardex DESC\n            )\n            SELECT COALESCE((SELECT SUM(total_venta) FROM period_sales), 0) AS sales_total,\n                   (SELECT COUNT(*) FROM period_sales) AS sales_count,\n                   COALESCE((SELECT AVG(total_venta) FROM period_sales), 0) AS average_ticket,\n                   (SELECT COUNT(DISTINCT id_cliente) FROM period_sales) AS unique_customers,\n                   (SELECT COUNT(*) FROM period_sales WHERE estado_sunat IN ('PENDIENTE', 'ERROR')) AS pending_sunat_count,\n                   COALESCE((SELECT SUM(total_ingreso) FROM operaciones.ingresos WHERE id_sucursal = ? AND fecha_emision >= ?::date AND fecha_emision < ?::date), 0) AS purchases_total,\n                   (SELECT COUNT(*) FROM operaciones.ingresos WHERE id_sucursal = ? AND fecha_emision >= ?::date AND fecha_emision < ?::date) AS purchases_count,\n                   (SELECT COUNT(*) FROM catalogos.productos products LEFT JOIN latest_stock stock ON stock.id_producto = products.id_producto WHERE products.estado = TRUE AND products.es_servicio = FALSE AND COALESCE(stock.stock_actual, 0) <= 0) AS zero_stock_count\n        ", [$branchId, $inicio, $fin, $branchId, $branchId, $inicio->toDateString(), $fin->toDateString(), $branchId, $inicio->toDateString(), $fin->toDateString()]);

        return [
            'salesTotal' => (float) ($row->sales_total ?? 0), 'salesCount' => (int) ($row->sales_count ?? 0),
            'averageTicket' => (float) ($row->average_ticket ?? 0), 'uniqueCustomers' => (int) ($row->unique_customers ?? 0),
            'pendingSunatCount' => (int) ($row->pending_sunat_count ?? 0), 'purchasesTotal' => (float) ($row->purchases_total ?? 0),
            'purchasesCount' => (int) ($row->purchases_count ?? 0), 'adjustmentsCount' => 0,
            'zeroStockCount' => (int) ($row->zero_stock_count ?? 0),
        ];
    }

    private function dailySalesByRange(int $branchId, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        return collect(DB::select("SELECT to_char(fecha_emision AT TIME ZONE 'America/Lima', 'DD/MM') AS label, COALESCE(SUM(total_venta), 0) AS total FROM operaciones.ventas WHERE id_sucursal = ? AND fecha_emision >= ? AND fecha_emision < ? GROUP BY (fecha_emision AT TIME ZONE 'America/Lima')::date, label ORDER BY (fecha_emision AT TIME ZONE 'America/Lima')::date", [$branchId, $inicio, $fin]))
            ->map(fn (object $row) => ['label' => $row->label, 'total' => (float) $row->total])->all();
    }

    private function paymentMethodsByRange(int $branchId, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        return collect(DB::select("SELECT method.nombre, SUM(payment.monto) AS total FROM operaciones.pagos_ventas payment INNER JOIN operaciones.ventas sale ON sale.id_venta = payment.id_venta INNER JOIN catalogos.metodos_pago method ON method.id_metodo = payment.id_metodo WHERE sale.id_sucursal = ? AND sale.fecha_emision >= ? AND sale.fecha_emision < ? AND payment.estado_pago = 'COMPLETADO' GROUP BY method.id_metodo, method.nombre ORDER BY total DESC LIMIT 4", [$branchId, $inicio, $fin]))
            ->map(fn (object $row) => ['name' => $row->nombre, 'total' => (float) $row->total])->all();
    }

    private function recentSalesByRange(int $branchId, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        return collect(DB::select("SELECT sale.id_venta, sale.serie, sale.correlativo, sale.total_venta, sale.fecha_emision, client.nombre_razon_social AS customer_name, receipt.descripcion AS receipt_name FROM operaciones.ventas sale INNER JOIN catalogos.clientes client ON client.id_cliente = sale.id_cliente INNER JOIN catalogos.tipos_comprobante receipt ON receipt.id_tipo_comprobante = sale.id_tipo_comprobante WHERE sale.id_sucursal = ? AND sale.fecha_emision >= ? AND sale.fecha_emision < ? ORDER BY sale.fecha_emision DESC LIMIT 6", [$branchId, $inicio, $fin]))
            ->map(fn (object $row) => ['id' => (int) $row->id_venta, 'document' => sprintf('%s-%s', $row->serie, str_pad((string) $row->correlativo, 6, '0', STR_PAD_LEFT)), 'customer' => $row->customer_name, 'receipt' => $row->receipt_name, 'total' => (float) $row->total_venta, 'issuedAt' => $row->fecha_emision])->all();
    }

    /** Predicción explicable: compara el rango actual con el inmediatamente anterior. */
    private function insights(int $branchId, array $summary, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        $duration = max(1, $inicio->diffInSeconds($fin));
        $prevStart = $inicio->copy()->subSeconds($duration);
        $previous = (float) DB::table('operaciones.ventas')
            ->where('id_sucursal', $branchId)
            ->where('fecha_emision', '>=', $prevStart)
            ->where('fecha_emision', '<', $inicio)
            ->sum('total_venta');
        $current = (float) ($summary['salesTotal'] ?? 0);
        $variation = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
        $now = Carbon::now('America/Lima')->utc();
        $reference = $fin->lessThan($now) ? $fin : $now;
        $elapsed = max(1, $inicio->diffInSeconds($reference));

        return [
            'salesVariation' => $variation,
            'uniqueCustomers' => (int) ($summary['uniqueCustomers'] ?? 0),
            'pendingSunatCount' => (int) ($summary['pendingSunatCount'] ?? 0),
            'projectedSales' => $fin->greaterThan(Carbon::now('America/Lima')->utc()) ? round($current * ($duration / $elapsed), 2) : null,
            'basis' => 'Comparación con el período anterior de igual duración.',
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'periodo'        => 'hoy',
            'summary'        => [
                'salesTotal'       => 0,
                'salesCount'       => 0,
                'averageTicket'    => 0,
                'purchasesTotal'   => 0,
                'purchasesCount'   => 0,
                'adjustmentsCount' => 0,
                'zeroStockCount'   => 0,
            ],
            'hourlySales'    => [],
            'esDiario'       => false,
            'paymentMethods' => [],
            'recentSales'    => [],
            'stockAlerts'    => [],
            'cashShift'      => null,
            'insights'       => [],
            'generatedAt'    => now()->toIso8601String(),
        ];
    }
}
