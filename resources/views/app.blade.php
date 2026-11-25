<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Pestaña del navegador con la marca de Datos Generales desde la primera carga.
        try {
            $marca = app(\App\Services\Configuracion\DatosGeneralesService::class)->identidad();
        } catch (\Throwable) {
            $marca = null;
        }
        $iconoMarca = $marca['logo_url'] ?? '/favicon.ico';
    @endphp
    <title inertia>{{ $marca['nombre_comercial'] ?? $marca['razon_social'] ?? config('app.name') }}</title>
    <link rel="icon" href="{{ $iconoMarca }}" data-marca>
    <link rel="apple-touch-icon" href="{{ $iconoMarca }}" data-marca>
    <meta name="theme-color" content="{{ $marca['color_primario'] ?? '#0e7490' }}">
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
