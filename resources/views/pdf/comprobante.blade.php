<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 30px; }
        .head, .summary { width: 100%; }
        .head td { vertical-align: top; }
        .issuer { width: 64%; padding: 10px 22px 10px 2px; }
        .document { border: 1px solid #111827; padding: 12px; text-align: center; width: 36%; }
        h1 { color: #111827; font-size: 13px; margin: 0 0 5px; text-transform: uppercase; }
        h2 { color: #111827; font-size: 12px; margin: 0 0 8px; }
        p { line-height: 1.55; margin: 2px 0; }
        .muted { color: #64748b; }
        .box { border: 1px solid #94a3b8; margin: 14px 0; padding: 9px 10px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #e5e7eb; border-top: 1px solid #64748b; border-bottom: 1px solid #64748b; color: #111827; font-size: 8px; letter-spacing: .25px; padding: 7px 5px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e5e7eb; padding: 7px 5px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals { margin-left: auto; width: 42%; }
        .totals td { padding: 5px 6px; }
        .total td { border-top: 1px solid #111827; color: #111827; font-size: 12px; font-weight: bold; }
        .label { color: #475569; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .box-table { border-collapse: separate; border-spacing: 0; margin: 14px 0; }
        .box-table .half { width: 50%; margin: 0; border: 1px solid #94a3b8; padding: 9px 10px; }
        .box-table .half + .half { border-left: 0; }
        .summary { margin-top: 10px; }
        .summary > tbody > tr > td, .summary td.letras { border: 0; vertical-align: top; }
        .letras { font-size: 8.5px; font-weight: bold; padding-top: 8px; padding-right: 14px; }
        .totals-inner td { padding: 5px 6px; }
        .footer { border-top: 1px solid #94a3b8; color: #475569; font-size: 8px; margin-top: 22px; padding-top: 10px; text-align: center; }
    </style>
</head>
<body>
@php
    $e = $comprobante['emisor'];
    $d = $comprobante['documento'];
    $c = $comprobante['cliente'];
    $t = $comprobante['totales'];
    $local = $e['establecimiento'] ?? [];
    // El local solo se agrega si existe domicilio fiscal y es otra dirección.
    $mostrarLocal = filled($local['direccion'] ?? null) && filled($e['direccion'] ?? null) && mb_strtoupper((string) ($local['direccion'] ?? '')) !== mb_strtoupper((string) ($e['direccion'] ?? ''));
    $identificado = $c['identificado'] ?? true;
    $esFactura = ($d['codigo_sunat'] ?? null) === '01';
    $dinero = fn ($valor) => 'S/ '.number_format((float) $valor, 2);
    $cantidad = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 3, '.', ','), '0'), '.');
@endphp
<table class="head"><tr>
    <td class="issuer">
        <h1>{{ $e['nombre_comercial'] ?: ($e['razon_social'] ?: config('app.name')) }}</h1>
        @if($e['nombre_comercial'] && $e['razon_social'] && mb_strtoupper($e['nombre_comercial']) !== mb_strtoupper($e['razon_social']))<p><strong>{{ $e['razon_social'] }}</strong></p>@endif
        <p>{{ $e['direccion'] ?: ($local['direccion'] ?? 'Dirección fiscal no configurada') }}</p>
        @if($mostrarLocal)<p>{{ $local['nombre'] ?? 'Establecimiento' }}: {{ $local['direccion'] }}</p>@endif
        @if(filled($local['telefono'] ?? null))<p>Tel.: {{ $local['telefono'] }}</p>@endif
    </td>
    <td class="document"><p><strong>RUC {{ $e['ruc'] ?: '—' }}</strong></p><h2>{{ $d['titulo'] }}</h2><p><strong>{{ $d['numero'] }}</strong></p></td>
</tr></table>

<table class="box-table"><tr>
    <td class="box half">
        <span class="label">{{ $esFactura ? 'Adquirente' : 'Cliente' }}</span><br>
        @if($identificado)
            <strong>{{ $esFactura ? 'Razón social' : 'Nombre' }}:</strong> {{ $c['nombre'] }}<br>
            <strong>{{ $c['tipo_documento_corto'] ?? $c['tipo_documento'] }}:</strong> {{ $c['numero_documento'] }}<br>
            @if($c['direccion'])<strong>Dirección:</strong> {{ $c['direccion'] }}@endif
        @else
            CLIENTES VARIOS
        @endif
    </td>
    <td class="box half">
        <span class="label">Emisión</span><br>
        <strong>Fecha:</strong> {{ $d['fecha'] ?? (filled($d['emitido_en']) ? \Illuminate\Support\Carbon::parse($d['emitido_en'])->timezone('America/Lima')->format('d/m/Y') : '—') }}
        &nbsp; <strong>Hora:</strong> {{ $d['hora'] ?? (filled($d['emitido_en']) ? \Illuminate\Support\Carbon::parse($d['emitido_en'])->timezone('America/Lima')->format('H:i:s') : '—') }}<br>
        <strong>Moneda:</strong> {{ $d['moneda'] ?? 'SOLES' }} &nbsp; <strong>Forma de pago:</strong> {{ $d['forma_pago'] ?? 'CONTADO' }}<br>
        @if(filled($comprobante['operacion']['cajero'] ?? null))<strong>Cajero:</strong> {{ $comprobante['operacion']['cajero'] }}@endif
        @if(filled($comprobante['operacion']['caja'] ?? null)) &nbsp; <strong>Caja:</strong> {{ $comprobante['operacion']['caja'] }}@endif
    </td>
</tr></table>

<table><thead><tr><th class="right">Cant.</th><th class="center">Unidad</th><th>Descripción</th><th class="right">P. unitario</th><th class="right">Importe</th></tr></thead><tbody>
@foreach($comprobante['items'] as $detalle)
<tr><td class="right">{{ $cantidad($detalle['cantidad']) }}</td><td class="center">{{ mb_strtoupper($detalle['unidad'] ?? $detalle['presentacion']) }}</td><td>{{ $detalle['nombre'] }}</td><td class="right">{{ $dinero($detalle['precio_unitario']) }}</td><td class="right">{{ $dinero($detalle['subtotal']) }}</td></tr>
@endforeach
</tbody></table>

<table class="summary"><tr>
    <td class="letras">@if(filled($t['total_letras'] ?? null)){{ $t['total_letras'] }}@endif</td>
    <td style="width:42%">
        <table class="totals-inner">
            <tr><td>Op. gravadas</td><td class="right">{{ $dinero($t['gravadas']) }}</td></tr>
            @if((float) $t['exoneradas'] > 0)<tr><td>Op. exoneradas</td><td class="right">{{ $dinero($t['exoneradas']) }}</td></tr>@endif
            @if((float) $t['descuento'] > 0)<tr><td>Descuentos</td><td class="right">-{{ $dinero($t['descuento']) }}</td></tr>@endif
            <tr><td>IGV{{ isset($t['igv_porcentaje']) ? ' ('.rtrim(rtrim(number_format((float) $t['igv_porcentaje'], 2), '0'), '.').'%)' : '' }}</td><td class="right">{{ $dinero($t['igv']) }}</td></tr>
            <tr class="total"><td>IMPORTE TOTAL</td><td class="right">{{ $dinero($t['total']) }}</td></tr>
        </table>
    </td>
</tr></table>

<div class="box"><span class="label">Pago</span><br>@foreach($comprobante['pagos'] as $pago)<strong>{{ $pago['metodo'] }}:</strong> {{ $dinero($pago['monto']) }}@if($pago['monto_recibido']) · Recibido: {{ $dinero($pago['monto_recibido']) }}@endif @if($pago['vuelto']) · Vuelto: {{ $dinero($pago['vuelto']) }}@endif @if($pago['referencia']) · N° operación: {{ $pago['referencia'] }}@endif<br>@endforeach</div>
<div class="footer">
    @if(filled($qr ?? null))
        <img src="{{ $qr }}" alt="Código QR del comprobante" width="92" height="92"><br>
    @endif
    {{ $comprobante['leyendas']['representacion'] ?? 'Representación impresa del comprobante electrónico' }}.<br>
    {{ $comprobante['leyendas']['consulta'] ?? '' }}<br>
    @if(filled($comprobante['hash_cpe'] ?? null))Resumen: {{ $comprobante['hash_cpe'] }}<br>@endif
    <strong>{{ $e['pie_ticket'] ?: 'Gracias por su compra.' }}</strong>
</div>
</body>
</html>
