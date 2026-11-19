<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Kardex valorizado</title>
    <style>
        @page { margin: 22px 24px 34px; }
        * { box-sizing: border-box; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 7.4px; margin: 0; }
        h1 { font-size: 13px; letter-spacing: .3px; margin: 0 0 2px; }
        .sub { color: #475569; font-size: 8px; margin: 0; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .head td { vertical-align: top; padding: 0; }
        .ficha { border-collapse: collapse; margin-top: 6px; }
        .ficha td { padding: 1.5px 10px 1.5px 0; font-size: 7.8px; }
        .ficha td.e { color: #475569; font-weight: bold; text-transform: uppercase; font-size: 6.8px; letter-spacing: .3px; }
        .resumen { border-collapse: collapse; float: right; }
        .resumen td { border: 1px solid #cbd5e1; padding: 4px 7px; text-align: right; }
        .resumen .e { color: #475569; font-size: 6.6px; text-transform: uppercase; display: block; }
        .resumen .v { font-size: 9px; font-weight: bold; }
        table.k { width: 100%; border-collapse: collapse; }
        .k th { background: #e2e8f0; border: 1px solid #94a3b8; font-size: 6.8px; padding: 3px 3px; text-align: center; text-transform: uppercase; letter-spacing: .2px; }
        .k td { border: 1px solid #cbd5e1; padding: 2.6px 3px; vertical-align: top; }
        .k tbody tr:nth-child(even) td { background: #f8fafc; }
        .n { text-align: right; white-space: nowrap; }
        .grupo { border-left: 1.4px solid #64748b !important; }
        .saldo-ini td { font-style: italic; background: #f1f5f9 !important; }
        .tot td { font-weight: bold; border-top: 1.4px solid #0f172a; background: #f1f5f9 !important; }
        .vacio { text-align: center; color: #64748b; padding: 10px; }
        .aviso { color: #b91c1c; font-weight: bold; margin-top: 6px; }
        .pie { color: #64748b; font-size: 6.8px; margin-top: 6px; }
    </style>
</head>
<body>
@php
    $k = $kardex;
    $cant = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
    $cu = fn ($v) => $v === null ? '' : 'S/ '.number_format((float) $v, 4, '.', ',');
    $tot = fn ($v) => $v === null ? '' : 'S/ '.number_format((float) $v, 2, '.', ',');
    $fecha = fn ($iso) => \App\Services\Inventario\ExportarKardexService::fecha($iso);
@endphp

<table class="head"><tr>
    <td>
        <h1>KARDEX VALORIZADO</h1>
        <p class="sub">{{ \App\Services\Inventario\ExportarKardexService::empresa($k) }}@if($k['empresa']['sucursal']) · Sucursal {{ $k['empresa']['sucursal'] }}@endif</p>
        <table class="ficha">
            <tr><td class="e">Producto</td><td>{{ $k['producto']['codigo'] ? $k['producto']['codigo'].' — ' : '' }}{{ $k['producto']['nombre'] }}</td></tr>
            <tr><td class="e">Unidad</td><td>{{ $k['producto']['unidad'] }}</td></tr>
            <tr><td class="e">Periodo</td><td>{{ $fecha($k['periodo']['desde']) }} al {{ $fecha($k['periodo']['hasta']) }}</td></tr>
            <tr><td class="e">Método</td><td>{{ $k['metodo'] }}</td></tr>
        </table>
    </td>
    <td style="width: 46%">
        <table class="resumen"><tr>
            <td><span class="e">Saldo inicial</span><span class="v">{{ $tot($k['saldo_inicial']['total']) }}</span><br>{{ $cant($k['saldo_inicial']['cantidad']) }} u.</td>
            <td><span class="e">Entradas</span><span class="v">{{ $tot($k['totales']['entradas_total']) }}</span><br>{{ $cant($k['totales']['entradas_cantidad']) }} u.</td>
            <td><span class="e">Salidas</span><span class="v">{{ $tot($k['totales']['salidas_total']) }}</span><br>{{ $cant($k['totales']['salidas_cantidad']) }} u.</td>
            <td><span class="e">Saldo final</span><span class="v">{{ $tot($k['saldo_final']['total']) }}</span><br>{{ $cant($k['saldo_final']['cantidad']) }} u.</td>
        </tr></table>
    </td>
</tr></table>

<table class="k">
    <thead>
        <tr>
            <th rowspan="2" style="width: 9%">Fecha</th>
            <th rowspan="2" style="width: 11%">Operación</th>
            <th rowspan="2" style="width: 10%">Documento</th>
            <th rowspan="2" style="width: 8%">Usuario</th>
            <th colspan="3" class="grupo">Entradas</th>
            <th colspan="3" class="grupo">Salidas</th>
            <th colspan="3" class="grupo">Saldo</th>
        </tr>
        <tr>
            @foreach (range(1, 3) as $g)
                <th class="grupo">Cant.</th><th>C. unit.</th><th>Total</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr class="saldo-ini">
            <td colspan="4">Saldo inicial al {{ $fecha($k['periodo']['desde']) }}</td>
            <td class="n grupo"></td><td></td><td></td>
            <td class="n grupo"></td><td></td><td></td>
            <td class="n grupo">{{ $cant($k['saldo_inicial']['cantidad']) }}</td><td class="n">{{ $cu($k['saldo_inicial']['costo_unitario']) }}</td><td class="n">{{ $tot($k['saldo_inicial']['total']) }}</td>
        </tr>
        @forelse ($k['movimientos'] as $m)
            <tr>
                <td>{{ $m['fecha'] }}</td>
                <td>{{ $m['operacion'] }}</td>
                <td>{{ $m['documento'] ?? '—' }}</td>
                <td>{{ $m['usuario'] ?? '' }}</td>
                @foreach (['entrada', 'salida', 'saldo'] as $grupo)
                    <td class="n grupo">{{ $cant($m[$grupo]['cantidad'] ?? null) }}</td>
                    <td class="n">{{ $cu($m[$grupo]['costo_unitario'] ?? null) }}</td>
                    <td class="n">{{ $tot($m[$grupo]['total'] ?? null) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="13" class="vacio">Sin movimientos en el periodo.</td></tr>
        @endforelse
        <tr class="tot">
            <td colspan="4">Totales del periodo</td>
            <td class="n grupo">{{ $cant($k['totales']['entradas_cantidad']) }}</td><td></td><td class="n">{{ $tot($k['totales']['entradas_total']) }}</td>
            <td class="n grupo">{{ $cant($k['totales']['salidas_cantidad']) }}</td><td></td><td class="n">{{ $tot($k['totales']['salidas_total']) }}</td>
            <td class="n grupo">{{ $cant($k['saldo_final']['cantidad']) }}</td><td class="n">{{ $cu($k['saldo_final']['costo_unitario']) }}</td><td class="n">{{ $tot($k['saldo_final']['total']) }}</td>
        </tr>
    </tbody>
</table>

@if ($k['truncado'])
    <p class="aviso">El periodo supera {{ \App\Services\Inventario\KardexValorizadoService::MAXIMO_MOVIMIENTOS }} movimientos: se muestran los primeros. Reduce el rango de fechas.</p>
@endif
<p class="pie">Cantidades en {{ mb_strtolower($k['producto']['unidad']) }}. Generado el {{ $k['generado']['en'] }}@if($k['generado']['por']) por {{ $k['generado']['por'] }}@endif.@unless($k['con_origen']) Los movimientos anteriores al registro de origen no indican documento.@endunless</p>

</body>
</html>
