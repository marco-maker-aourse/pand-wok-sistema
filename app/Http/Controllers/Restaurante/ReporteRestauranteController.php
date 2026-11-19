<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Services\Restaurante\ReportesRestauranteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReporteRestauranteController extends Controller
{
    public function __invoke(Request $request, ReportesRestauranteService $servicio): Response
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d', 'required_with:hasta'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'required_with:desde', 'after_or_equal:desde'],
            'sucursal' => ['nullable', 'integer', 'min:0'],
        ]);
        $usuario = $request->user();
        $sucursales = $usuario->sucursalesDisponibles()->map->only(['id_sucursal', 'nombre'])->values();
        $sede = (int) ($filtros['sucursal'] ?? $usuario->idSucursalActiva());
        $ids = $sucursales->pluck('id_sucursal')->map(fn ($id) => (int) $id);
        abort_if($sede !== 0 && ! $ids->containsStrict($sede), 403);

        $hoy = CarbonImmutable::today('America/Lima');
        $desde = isset($filtros['desde']) ? CarbonImmutable::parse($filtros['desde'], 'America/Lima') : $hoy->subDays(6);
        $hasta = isset($filtros['hasta']) ? CarbonImmutable::parse($filtros['hasta'], 'America/Lima') : $hoy;
        if ($desde->diffInDays($hasta) >= 90) {
            throw ValidationException::withMessages(['hasta' => 'Consulta hasta 90 días por vez.']);
        }

        return Inertia::render('Restaurante/Reportes', [
            'filtros' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'sucursal' => $sede],
            'sucursales' => $sucursales,
            'reporte' => $servicio->consultar($sede === 0 ? $ids->all() : [$sede], $desde, $hasta),
        ]);
    }
}
