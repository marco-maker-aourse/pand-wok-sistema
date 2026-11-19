<?php

namespace App\Services\Asistente;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioAsistenteOperativo
{
    public function __construct(private readonly ClasificadorConsultasOperativas $clasificador)
    {
    }

    public function resolverConsultaOperativa(string $mensaje, int $sucursalId, int $usuarioId, Session $sesion): ?string
    {
        $consulta = Str::lower(Str::ascii($mensaje));
        $fecha = $this->fechaIndicada($consulta);
        $intencion = $this->clasificador->clasificar($consulta);

        // Una palabra después de un ranking (por ejemplo “Leche”) conserva el contexto de la conversación.
        $rankingPrevio = $sesion->get('asistente.ultimo_ranking_productos', []);
        if ($intencion === 'abierta' && is_array($rankingPrevio) && in_array($consulta, $rankingPrevio, true)) {
            return $this->responderDetalleProducto($consulta, $sucursalId);
        }

        if ($fecha && $intencion === 'seguimiento_documental') {
            $contexto = $sesion->get('asistente.ultima_consulta_documental');
            if (is_array($contexto)) {
                $tipo = ($contexto['tipo'] ?? null) === '03' ? 'boleta' : 'factura';
                return $this->responderComprobante($tipo, $sucursalId, $fecha, $sesion);
            }
        }

        return match ($intencion) {
            'ayuda' => $this->responderAyuda(),
            'analisis' => $this->responderAnalisis($sucursalId),
            'recomendaciones' => $this->responderRecomendaciones($sucursalId),
            'producto_mas_vendido' => $this->responderProductoMasVendido($sucursalId, $this->rangoSolicitado($consulta), $sesion),
            'comprobante' => $this->responderComprobante($consulta, $sucursalId, $fecha, $sesion),
            'metodos_pago' => $this->responderMetodosPago(),
            'stock_critico' => $this->responderStock($sucursalId),
            'stock_producto' => $this->responderStockProducto($consulta, $sucursalId),
            'caja' => $this->responderCaja($sucursalId, $usuarioId),
            'vencimientos' => $this->responderVencimientos($sucursalId),
            'cobros' => $this->responderCobros($sucursalId, $this->rangoSolicitado($consulta)),
            'clientes' => $this->responderEntidades('catalogos.clientes', 'nombre_razon_social', 'clientes'),
            'proveedores' => $this->responderEntidades('catalogos.proveedores', 'razon_social', 'proveedores'),
            'compras' => $this->responderCompras($sucursalId, $this->rangoSolicitado($consulta)),
            'estado_sunat' => $this->responderEstadoSunat($sucursalId, $this->rangoSolicitado($consulta)),
            'ventas' => ($rango = $this->rangoSolicitado($consulta)) ? $this->responderVentasPorRango($sucursalId, ...$rango) : ($fecha ? $this->responderVentasPorFecha($sucursalId, $fecha) : null),
            'catalogo' => $this->responderCatalogo(),
            default => null,
        };
    }

    private function responderAyuda(): string
    {
        return $this->respuesta('Puedo consultar comprobantes, ventas por fecha o período, cobros, métodos de pago, caja, stock, vencimientos, compras, clientes y proveedores. También puedo mostrar una tendencia de ventas o prioridades operativas.', 'Por ejemplo, puedes preguntar: “¿cuál es la última factura?”, “ventas de los últimos 7 días”, “¿qué recomiendas revisar?” o “proyección de ventas”.');
    }

    private function responderAnalisis(int $sucursalId): string
    {
        $ahora = CarbonImmutable::now('America/Lima');
        $fin = $ahora->copy()->addDay()->startOfDay()->utc();
        $inicioActual = $fin->subDays(7);
        $inicioAnterior = $inicioActual->subDays(7);
        $ventasActuales = (float) DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicioActual)->where('fecha_emision', '<', $fin)->sum('total_venta');
        $ventasAnteriores = (float) DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicioAnterior)->where('fecha_emision', '<', $inicioActual)->sum('total_venta');
        $variacion = $ventasAnteriores > 0 ? (($ventasActuales - $ventasAnteriores) / $ventasAnteriores) * 100 : null;

        [$inicioHoy, $finHoy] = $this->rangoDia($ahora);
        $ventasHoy = (float) DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicioHoy)->where('fecha_emision', '<', $finHoy)->sum('total_venta');
        $horasTranscurridas = max(1, $ahora->hour + ($ahora->minute / 60));
        $proyeccion = $ventasHoy > 0 ? $ventasHoy * (12 / min(12, $horasTranscurridas)) : 0;
        $mensaje = 'En los últimos 7 días las ventas suman S/ ' . number_format($ventasActuales, 2) . '.';
        $mensaje .= $variacion === null ? ' Aún no hay un período anterior comparable.' : ' Frente a los 7 días previos, la variación es de ' . ($variacion >= 0 ? '+' : '') . number_format($variacion, 1) . '%.';
        $mensaje .= ' Hoy se registran S/ ' . number_format($ventasHoy, 2) . '.';

        return $this->respuesta($mensaje, $proyeccion > 0 ? 'Estimación de cierre de hoy: S/ ' . number_format($proyeccion, 2) . ', calculada con el ritmo actual; úsala como orientación, no como resultado definitivo.' : 'Cuando se registren ventas hoy, podré estimar el cierre usando el ritmo real de la jornada.');
    }

    private function responderProductoMasVendido(int $sucursalId, ?array $rango, Session $sesion): string
    {
        $fin = CarbonImmutable::now('America/Lima')->addDay()->startOfDay()->utc();
        [$inicio, $fin] = $rango ?? [$fin->subDays(30), $fin];
        $productos = DB::table('operaciones.detalle_ventas as detalle')
            ->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'detalle.id_venta')
            ->join('inventario.precio_producto as precio', 'precio.id_precio', '=', 'detalle.id_precio')
            ->join('catalogos.productos as producto', 'producto.id_producto', '=', 'precio.id_producto')
            ->join('catalogos.unidades_medida as unidad', 'unidad.id_unidad', '=', 'producto.id_unidad_base')
            ->where('venta.id_sucursal', $sucursalId)
            ->where('venta.fecha_emision', '>=', $inicio)
            ->where('venta.fecha_emision', '<', $fin)
            ->groupBy('producto.id_producto', 'producto.nombre', 'unidad.nombre')
            ->orderByDesc(DB::raw('SUM(detalle.cantidad * precio.factor_conversion)'))
            ->limit(3)
            ->get([
                'producto.nombre',
                'unidad.nombre as unidad',
                DB::raw('SUM(detalle.cantidad * precio.factor_conversion) as cantidad_base'),
                DB::raw('SUM(detalle.subtotal) as total_vendido'),
            ]);

        if ($productos->isEmpty()) {
            return $this->respuesta('No hay ventas registradas en los últimos 30 días para calcular un producto más vendido.', 'Cuando se registren ventas, podré mostrar el ranking por cantidad y por importe vendido.');
        }

        $sesion->put('asistente.ultimo_ranking_productos', $productos->pluck('nombre')->map(fn ($nombre) => Str::lower(Str::ascii($nombre)))->all());
        $principal = $productos->first();
        $ranking = $productos->skip(1)->map(fn ($producto) => "{$producto->nombre}: {$producto->cantidad_base} {$producto->unidad}, S/ " . number_format((float) $producto->total_vendido, 2))->implode('; ');
        $mensaje = "El producto más vendido en los últimos 30 días es {$principal->nombre}, con {$principal->cantidad_base} {$principal->unidad} y ventas por S/ " . number_format((float) $principal->total_vendido, 2) . '.';
        if ($ranking !== '') $mensaje .= ' Le siguen: ' . $ranking . '.';

        return $this->respuesta($mensaje, 'Puedes consultar el stock de cualquiera de estos productos o pedir una recomendación de reposición.', 'El período analizado comprende desde ' . $inicio->setTimezone('America/Lima')->format('d/m/Y') . ' hasta ' . $fin->subSecond()->setTimezone('America/Lima')->format('d/m/Y') . '. El ranking se calcula en unidades base para comparar presentaciones correctamente.');
    }

    private function responderDetalleProducto(string $nombre, int $sucursalId): string
    {
        $producto = DB::table('catalogos.productos as producto')
            ->join('catalogos.unidades_medida as unidad', 'unidad.id_unidad', '=', 'producto.id_unidad_base')
            ->whereRaw('LOWER(producto.nombre) = ?', [$nombre])
            ->select('producto.id_producto', 'producto.nombre', 'unidad.nombre as unidad')
            ->first();
        if (! $producto) return 'No encontré ese producto activo en esta sucursal.';

        $stock = DB::table('inventario.kardex')->where('id_sucursal', $sucursalId)->where('id_producto', $producto->id_producto)->latest('id_kardex')->value('stock_actual');
        $inicio = CarbonImmutable::now('America/Lima')->subDays(30)->startOfDay()->utc();
        $vendido = DB::table('operaciones.detalle_ventas as detalle')->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'detalle.id_venta')->join('inventario.precio_producto as precio', 'precio.id_precio', '=', 'detalle.id_precio')->where('venta.id_sucursal', $sucursalId)->where('precio.id_producto', $producto->id_producto)->where('venta.fecha_emision', '>=', $inicio)->sum(DB::raw('detalle.cantidad * precio.factor_conversion'));
        return $this->respuesta("{$producto->nombre} tiene " . number_format((float) ($stock ?? 0), 2) . " {$producto->unidad} disponibles. En los últimos 30 días se vendieron " . number_format((float) $vendido, 2) . " {$producto->unidad}.", 'Puedes consultar también sus presentaciones, precio o registrar una entrada si necesitas reponerlo.');
    }

    private function responderRecomendaciones(int $sucursalId): string
    {
        [$inicio, $fin] = $this->rangoDia(CarbonImmutable::now('America/Lima'));
        $pendientesSunat = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)->whereIn('estado_sunat', ['PENDIENTE', 'ERROR'])->count();
        $porVencer = DB::table('inventario.lotes')->where('id_sucursal', $sucursalId)->where('estado', true)->where('stock', '>', 0)->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(7)->toDateString()])->count();
        $sinStock = DB::selectOne("WITH ultimo_kardex AS (SELECT DISTINCT ON (id_producto) id_producto, stock_actual FROM inventario.kardex WHERE id_sucursal = ? ORDER BY id_producto, id_kardex DESC) SELECT COUNT(*) AS total FROM catalogos.productos producto LEFT JOIN ultimo_kardex kardex ON kardex.id_producto = producto.id_producto WHERE producto.estado = TRUE AND producto.es_servicio = FALSE AND COALESCE(kardex.stock_actual, 0) <= 0", [$sucursalId]);
        $prioridades = [];
        if ((int) ($sinStock->total ?? 0) > 0) $prioridades[] = (int) $sinStock->total . ' producto(s) sin stock';
        if ($porVencer > 0) $prioridades[] = $porVencer . ' lote(s) por vencer en 7 días';
        if ($pendientesSunat > 0) $prioridades[] = $pendientesSunat . ' comprobante(s) pendientes o con error';

        return $prioridades === []
            ? $this->respuesta('No detecté alertas operativas prioritarias para hoy.', 'Puedes revisar la tendencia de ventas o el arqueo de caja para continuar el control diario.')
            : $this->respuesta('Prioridades detectadas: ' . implode('; ', $prioridades) . '.', 'Te sugiero atender primero los documentos pendientes, luego el inventario sin stock y finalmente los lotes próximos a vencer.');
    }

    private function responderComprobante(string $consulta, int $sucursalId, ?CarbonImmutable $fecha, Session $sesion): string
    {
        $tipo = Str::contains($consulta, 'factura') ? '01' : (Str::contains($consulta, 'boleta') ? '03' : null);
        $porFecha = $fecha !== null;
        $fechaConsulta = $fecha ?? CarbonImmutable::now('America/Lima');
        $query = DB::table('operaciones.ventas as venta')->join('catalogos.tipos_comprobante as tipo', 'tipo.id_tipo_comprobante', '=', 'venta.id_tipo_comprobante')->where('venta.id_sucursal', $sucursalId)->when($tipo, fn ($builder) => $builder->where('tipo.codigo_sunat', $tipo));
        if ($porFecha) {
            [$inicio, $fin] = $this->rangoDia($fechaConsulta);
            $query->where('venta.fecha_emision', '>=', $inicio)->where('venta.fecha_emision', '<', $fin);
        }
        $venta = $query->orderByDesc('venta.fecha_emision')->select('venta.serie', 'venta.correlativo', 'venta.fecha_emision', 'venta.total_venta', 'tipo.descripcion')->first();
        $sesion->put('asistente.ultima_consulta_documental', ['fecha' => $fechaConsulta->toDateString(), 'tipo' => $tipo]);
        $rango = $porFecha ? ' para el ' . $fechaConsulta->format('d/m/Y') : '';
        if (! $venta) return $this->respuesta("No se encontraron comprobantes{$rango} en esta sucursal.", 'Puedes consultar otra fecha, revisar el historial de ventas o emitir un nuevo comprobante desde el punto de venta.');

        $numero = sprintf('%s-%06d', $venta->serie, $venta->correlativo);
        $emitida = CarbonImmutable::parse($venta->fecha_emision)->setTimezone('America/Lima')->format('d/m/Y H:i');
        return $this->respuesta("El último comprobante{$rango} es {$venta->descripcion} {$numero}, emitido el {$emitida}, por S/ " . number_format((float) $venta->total_venta, 2) . '.', 'Si necesitas validar el envío o descargar el archivo, puedes revisarlo en el historial de comprobantes.');
    }

    private function responderVentasPorFecha(int $sucursalId, CarbonImmutable $fecha): string
    {
        [$inicio, $fin] = $this->rangoDia($fecha);
        $resumen = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total_venta), 0) as total, COALESCE(AVG(total_venta), 0) as ticket_promedio')->first();
        $cantidad = (int) ($resumen->cantidad ?? 0);
        return $cantidad === 0
            ? $this->respuesta('No se registraron ventas el ' . $fecha->format('d/m/Y') . '.', 'Puedes revisar si hubo un turno abierto o consultar otro día.')
            : $this->respuesta('El ' . $fecha->format('d/m/Y') . ' se registraron ' . $cantidad . ' venta(s) por S/ ' . number_format((float) $resumen->total, 2) . '.', 'Si lo necesitas, puedo ayudarte a revisar los comprobantes o cobros de ese día.', 'El ticket promedio fue S/ ' . number_format((float) $resumen->ticket_promedio, 2) . '.');
    }

    private function responderStock(int $sucursalId): string
    {
        $productos = DB::select("WITH ultimo_kardex AS (SELECT DISTINCT ON (id_producto) id_producto, stock_actual FROM inventario.kardex WHERE id_sucursal = ? ORDER BY id_producto, id_kardex DESC) SELECT producto.nombre FROM catalogos.productos producto LEFT JOIN ultimo_kardex kardex ON kardex.id_producto = producto.id_producto WHERE producto.estado = TRUE AND producto.es_servicio = FALSE AND COALESCE(kardex.stock_actual, 0) <= 0 ORDER BY producto.nombre LIMIT 5", [$sucursalId]);
        return $productos === []
            ? $this->respuesta('No hay productos sin existencias en esta sucursal.', 'El inventario no presenta quiebres de stock en este momento.')
            : $this->respuesta('Productos sin existencias: ' . collect($productos)->pluck('nombre')->implode(', ') . (count($productos) === 5 ? '. Se muestran los primeros 5.' : '.'), 'Conviene registrar una entrada de mercadería antes de intentar venderlos.');
    }

    private function responderStockProducto(string $consulta, int $sucursalId): string
    {
        preg_match('/(?:stock|existencias?)\s+(?:de|del|para)\s+(.+)/', $consulta, $coincidencia);
        $termino = trim((string) ($coincidencia[1] ?? ''));
        if ($termino === '') return $this->respuesta('Indica el nombre del producto que deseas consultar.', 'Por ejemplo: “stock de arroz”.');

        $producto = DB::table('catalogos.productos as producto')
            ->leftJoin('inventario.kardex as kardex', function ($join) use ($sucursalId) {
                $join->on('kardex.id_producto', '=', 'producto.id_producto')->where('kardex.id_sucursal', '=', $sucursalId);
            })
            ->leftJoin('catalogos.unidades_medida as unidad', 'unidad.id_unidad', '=', 'producto.id_unidad_base')
            ->where('producto.estado', true)
            ->whereRaw('LOWER(producto.nombre) LIKE ?', ['%' . $termino . '%'])
            ->orderByDesc('kardex.id_kardex')
            ->select('producto.nombre', 'kardex.stock_actual', 'unidad.nombre as unidad')
            ->first();

        if (! $producto) return $this->respuesta("No encontré un producto activo con el nombre “{$termino}”.", 'Verifica el nombre o búscalo desde el catálogo de productos.');
        $stock = (float) ($producto->stock_actual ?? 0);
        return $this->respuesta("{$producto->nombre} tiene {$stock} {$producto->unidad} disponibles.", $stock > 0 ? 'El dato corresponde al último movimiento registrado para esta sucursal.' : 'Conviene registrar una entrada antes de ofrecerlo en el punto de venta.');
    }

    private function responderEstadoSunat(int $sucursalId, ?array $rango): string
    {
        [$inicio, $fin] = $rango ?? $this->rangoDia(CarbonImmutable::now('America/Lima'));
        $estados = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)->groupBy('estado_sunat')->orderBy('estado_sunat')->selectRaw('estado_sunat, COUNT(*) as cantidad')->pluck('cantidad', 'estado_sunat');
        if ($estados->isEmpty()) return $this->respuesta('No hay comprobantes emitidos en el período consultado.', 'Cuando existan ventas emitidas, podrás consultar su estado de envío y respuesta.');
        $detalle = $estados->map(fn ($cantidad, $estado) => "{$estado}: {$cantidad}")->implode('; ');
        return $this->respuesta('Estado de comprobantes: ' . $detalle . '.', 'Los estados pendientes o con error requieren revisión desde el historial de comprobantes.');
    }

    private function responderCaja(int $sucursalId, int $usuarioId): string
    {
        $turno = DB::table('operaciones.turnos_caja')->where('id_sucursal', $sucursalId)->where('id_usuario', $usuarioId)->where('estado', 'ABIERTO')->latest('fecha_apertura')->first();
        if (! $turno) return $this->respuesta('No tienes un turno de caja abierto en esta sucursal.', 'Puedes abrirlo desde Caja antes de registrar cobros en el punto de venta.');
        return $this->respuesta('Tu turno está abierto desde ' . CarbonImmutable::parse($turno->fecha_apertura)->setTimezone('America/Lima')->format('d/m/Y H:i') . ', con fondo inicial de S/ ' . number_format((float) $turno->monto_apertura, 2) . '.', 'El cierre quedará disponible cuando termines las operaciones del turno.');
    }

    private function responderVencimientos(int $sucursalId): string
    {
        $lotes = DB::table('inventario.lotes as lote')->join('catalogos.productos as producto', 'producto.id_producto', '=', 'lote.id_producto')->where('lote.id_sucursal', $sucursalId)->where('lote.estado', true)->where('lote.stock', '>', 0)->whereBetween('lote.fecha_vencimiento', [now()->toDateString(), now()->addDays(30)->toDateString()])->orderBy('lote.fecha_vencimiento')->limit(5)->get(['producto.nombre', 'lote.fecha_vencimiento']);
        return $lotes->isEmpty()
            ? $this->respuesta('No hay lotes con vencimiento dentro de los próximos 30 días.', 'El inventario no presenta alertas de vencimiento cercanas.')
            : $this->respuesta('Próximos vencimientos: ' . $lotes->map(fn ($lote) => "{$lote->nombre} ({$lote->fecha_vencimiento})")->implode(', ') . '.', 'Considera priorizar estos productos en la venta o revisar su rotación.');
    }

    private function responderCobros(int $sucursalId, ?array $rango): string
    {
        [$inicio, $fin] = $rango ?? $this->rangoDia(CarbonImmutable::now('America/Lima'));
        $cobros = DB::table('operaciones.pagos_ventas as pago')->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'pago.id_venta')->join('catalogos.metodos_pago as metodo', 'metodo.id_metodo', '=', 'pago.id_metodo')->where('venta.id_sucursal', $sucursalId)->where('venta.fecha_emision', '>=', $inicio)->where('venta.fecha_emision', '<', $fin)->where('pago.estado_pago', 'COMPLETADO')->groupBy('metodo.nombre')->orderByDesc(DB::raw('SUM(pago.monto)'))->get([DB::raw('metodo.nombre as metodo'), DB::raw('SUM(pago.monto) as total')]);
        if ($cobros->isEmpty()) return $this->respuesta('No se registraron cobros completados en el período consultado.', 'Puedes revisar que las ventas hayan sido confirmadas o consultar otra fecha.');
        $detalle = $cobros->map(fn ($cobro) => "{$cobro->metodo}: S/ " . number_format((float) $cobro->total, 2))->implode('; ');
        return $this->respuesta('Cobros registrados: ' . $detalle . '.', 'Los importes corresponden únicamente a pagos completados.');
    }

    private function preguntaPorMetodosPago(string $consulta): bool
    {
        return preg_match('/(metodos?|formas?)\s+de\s+pagos?/', $consulta) === 1
            && Str::contains($consulta, ['hay', 'disponible', 'configurado', 'sistema', 'acepta', 'usar']);
    }

    private function responderMetodosPago(): string
    {
        $metodos = DB::table('catalogos.metodos_pago')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['nombre', 'tipo', 'requiere_referencia', 'comision_porcentaje', 'comision_fija']);

        if ($metodos->isEmpty()) {
            return $this->respuesta('No hay métodos de pago activos configurados todavía.', 'Puedes registrarlos desde el catálogo de métodos de pago antes de realizar cobros.');
        }

        $detalle = $metodos->map(function ($metodo) {
            $condicion = $metodo->requiere_referencia ? 'requiere referencia' : 'sin referencia';
            $comision = (float) $metodo->comision_porcentaje > 0 || (float) $metodo->comision_fija > 0
                ? ', comisión ' . number_format((float) $metodo->comision_porcentaje, 2) . '% + S/ ' . number_format((float) $metodo->comision_fija, 2)
                : ', sin comisión';
            return "{$metodo->nombre} ({$metodo->tipo}, {$condicion}{$comision})";
        })->implode('; ');

        return $this->respuesta('Hay ' . $metodos->count() . ' método(s) de pago activo(s): ' . $detalle . '.', 'Estos son los métodos que el punto de venta podrá mostrar al momento de cobrar.', 'Los métodos que requieren referencia pedirán el número de operación antes de confirmar el pago.');
    }

    private function responderCompras(int $sucursalId, ?array $rango): string
    {
        [$inicio, $fin] = $rango ?? $this->rangoDia(CarbonImmutable::now('America/Lima'));
        $resumen = DB::table('operaciones.ingresos')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio->toDateString())->where('fecha_emision', '<', $fin->toDateString())->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total_ingreso), 0) as total')->first();
        $cantidad = (int) ($resumen->cantidad ?? 0);
        return $cantidad === 0
            ? $this->respuesta('No se registraron ingresos de mercadería en el período consultado.', 'Puedes registrar una entrada cuando recibas productos del proveedor.')
            : $this->respuesta('Se registraron ' . $cantidad . ' ingreso(s) de mercadería por S/ ' . number_format((float) $resumen->total, 2) . '.', 'El detalle de productos y lotes está disponible en el historial de entradas.');
    }

    private function responderEntidades(string $tabla, string $campoNombre, string $etiqueta): string
    {
        $cantidad = DB::table($tabla)->where('estado', true)->count();
        $muestra = DB::table($tabla)->where('estado', true)->orderBy($campoNombre)->limit(3)->pluck($campoNombre)->filter()->implode(', ');
        return $cantidad === 0
            ? $this->respuesta("No hay {$etiqueta} activos registrados.", 'Puedes crear el primer registro desde el módulo correspondiente.')
            : $this->respuesta("Hay {$cantidad} {$etiqueta} activos registrados. Algunos son: {$muestra}.", 'Puedes usar la búsqueda del módulo para revisar o actualizar un registro específico.');
    }

    private function responderCatalogo(): string
    {
        $cantidad = DB::table('catalogos.productos')->where('estado', true)->count();
        return $this->respuesta("El catálogo tiene {$cantidad} producto(s) activo(s).", 'Si buscas uno en particular, escribe su nombre junto con la palabra producto para consultar su disponibilidad.');
    }

    private function responderVentasPorRango(int $sucursalId, CarbonImmutable $inicio, CarbonImmutable $fin): string
    {
        $resumen = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total_venta), 0) as total')->first();
        return $this->respuesta('En el período solicitado se registraron ' . (int) ($resumen->cantidad ?? 0) . ' venta(s) por S/ ' . number_format((float) ($resumen->total ?? 0), 2) . '.', 'Puedes pedir el detalle de cobros, comprobantes o una fecha específica.');
    }

    private function rangoSolicitado(string $consulta): ?array
    {
        if (! preg_match('/(?:ultimos?|pasados?)\s+(\d{1,3})\s+dias?/', $consulta, $coincidencia)) return null;
        $dias = max(1, min(365, (int) $coincidencia[1]));
        $fin = CarbonImmutable::now('America/Lima')->addDay()->startOfDay()->utc();
        return [$fin->subDays($dias), $fin];
    }

    private function respuesta(string $mensaje, string $siguientePaso, ?string $detalle = null): string
    {
        $respuesta = "Resumen: {$mensaje}";
        if ($detalle) $respuesta .= "\n\nDetalle: {$detalle}";
        return $respuesta . "\n\nSiguiente paso: {$siguientePaso}";
    }

    private function fechaIndicada(string $consulta): ?CarbonImmutable
    {
        $hoy = CarbonImmutable::now('America/Lima')->startOfDay();
        if (Str::contains($consulta, 'ayer')) return $hoy->subDay();
        if (preg_match('/(\d{1,3})\s+dias?\s+(antes|atras)/', $consulta, $coincidencia)) return $hoy->subDays((int) $coincidencia[1]);
        return Str::contains($consulta, ['hoy', 'dia de hoy']) ? $hoy : null;
    }

    private function rangoDia(CarbonImmutable $fecha): array
    {
        return [$fecha->startOfDay()->utc(), $fecha->addDay()->startOfDay()->utc()];
    }
}
