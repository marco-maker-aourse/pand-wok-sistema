<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TerminalPos;
use App\Services\Dashboard\DashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPageController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $metrics): Response
    {
        $user = $request->user();
        $branchId = $user->idSucursalActiva();
        $userId = $user?->id_usuario;

        $periodo = $request->query('periodo', 'hoy');
        if (! in_array($periodo, ['hoy', 'semana', '2_semanas', 'mes', '3_meses', 'personalizado'], true)) {
            $periodo = 'hoy';
        }

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d', 'required_with:hasta'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'required_with:desde', 'after_or_equal:desde'],
        ]);

        // El rango personalizado se limita a un año para preservar tiempo de respuesta predecible.
        if ($periodo === 'personalizado' && filled($filtros['desde'] ?? null) && filled($filtros['hasta'] ?? null)) {
            $desde = CarbonImmutable::createFromFormat('Y-m-d', $filtros['desde'], 'America/Lima')->startOfDay();
            $hasta = CarbonImmutable::createFromFormat('Y-m-d', $filtros['hasta'], 'America/Lima')->endOfDay();
            abort_if($desde->diffInDays($hasta) > 365, 422, 'El rango máximo de consulta es de 366 días.');
            $data = $metrics->forDateRange($branchId, $desde, $hasta, $userId);
        } else {
            // Conserva los períodos rápidos solicitados por la interfaz (7, 14, 30 y 90 días).
            // Antes se reemplazaban erróneamente por "hoy" en cada solicitud.
            $periodo = $periodo === 'personalizado' ? 'hoy' : $periodo;
            $data = $metrics->forPeriod($branchId, $periodo, $userId);
        }
        $sucursalNombre = $user?->sucursal?->nombre ?? 'Sede Central';

        return Inertia::render('DashboardOverview', [
            'metrics'             => $data,
            'puedeVerReportesRestaurante' => $user->hasPermission('reportes.restaurante'),
            'sucursalActual'      => $sucursalNombre,
            'periodoActual'       => $periodo,
            'filtrosFecha'        => ['desde' => $filtros['desde'] ?? null, 'hasta' => $filtros['hasta'] ?? null],
            'terminales'           => TerminalPos::query()
                ->where('id_sucursal', $branchId)
                ->where('activo', true)
                ->whereHas('impresora', fn ($query) => $query->where('activo', true))
                ->with('impresora:id_impresora,nombre,activo')
                ->orderBy('nombre')
                ->get(['id_terminal', 'id_sucursal', 'id_impresora', 'codigo', 'nombre']),

            // Props de compatibilidad
            'resumen'             => $data['summary'] ?? [],
            'ventas_hoy'          => $data['summary']['salesTotal'] ?? 0,
            'comprobantes_hoy'    => $data['summary']['salesCount'] ?? 0,
            'ticket_promedio'     => $data['summary']['averageTicket'] ?? 0,
            'compras_hoy'         => $data['summary']['purchasesTotal'] ?? 0,
            'movimientos_compras' => $data['summary']['adjustmentsCount'] ?? 0,
            'sin_stock'           => $data['summary']['zeroStockCount'] ?? 0,
            'turno_actual'        => $data['cashShift'] ?? null,
            'ventas_horas'        => collect($data['hourlySales'] ?? [])->pluck('total')->toArray(),
            'sucursal_actual'     => $sucursalNombre,
            'proximos_vencer'     => $data['proximos_vencer'] ?? [],
        ]);
    }
}
