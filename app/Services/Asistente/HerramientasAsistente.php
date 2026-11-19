<?php

namespace App\Services\Asistente;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Herramientas de SOLO LECTURA que el modelo de lenguaje puede invocar.
 *
 * Reglas de seguridad:
 *  - La sucursal y el usuario salen siempre de la sesión, nunca de los argumentos del modelo.
 *  - Cada herramienta exige un permiso; el modelo solo recibe las que el usuario puede usar
 *    y `ejecutar()` vuelve a comprobarlo por si el modelo pide otra.
 *  - Los argumentos se validan y acotan (fechas, límites, longitud de textos).
 *  - Devuelven datos estructurados; la redacción queda a cargo del modelo.
 */
class HerramientasAsistente
{
    private const ZONA = 'America/Lima';
    private const MAX_DIAS_RANGO = 366;

    /** Definición de cada herramienta: permiso requerido, descripción y parámetros (JSON Schema). */
    private function catalogo(): array
    {
        $rango = [
            'desde' => ['type' => 'string', 'description' => 'Fecha inicial inclusive, formato YYYY-MM-DD. Si se omite, hoy.'],
            'hasta' => ['type' => 'string', 'description' => 'Fecha final inclusive, formato YYYY-MM-DD. Si se omite, igual a desde.'],
        ];

        $comparacion = [
            'desde_a' => ['type' => 'string', 'description' => 'Inicio del período A (el actual), YYYY-MM-DD.'],
            'hasta_a' => ['type' => 'string', 'description' => 'Fin del período A, YYYY-MM-DD.'],
            'desde_b' => ['type' => 'string', 'description' => 'Inicio del período B (el de referencia), YYYY-MM-DD.'],
            'hasta_b' => ['type' => 'string', 'description' => 'Fin del período B, YYYY-MM-DD.'],
        ];

        return [
            'resumen_ventas' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Consultando ventas',
                'descripcion' => 'Total vendido, número de comprobantes y ticket promedio en un rango de fechas, con el desglose por día (hasta 31 días). Mismos criterios que el tablero.',
                'parametros' => $rango,
            ],
            'comparar_ventas' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Comparando períodos',
                'descripcion' => 'Compara las ventas de dos períodos y devuelve ya calculadas la diferencia en soles y la variación porcentual. Úsala siempre que pidan comparar.',
                'parametros' => $comparacion,
                'requeridos' => ['desde_a', 'hasta_a', 'desde_b', 'hasta_b'],
            ],
            'ventas_por_vendedor' => [
                'permiso' => 'reportes.ver',
                'etiqueta' => 'Consultando ventas por vendedor',
                'descripcion' => 'Ventas agrupadas por usuario/vendedor (cajero) en un rango de fechas.',
                'parametros' => $rango,
            ],
            'productos_mas_vendidos' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Calculando productos más vendidos',
                'descripcion' => 'Ranking de productos más vendidos por cantidad (en unidad base) e importe en un rango de fechas. Si no se indica rango, últimos 30 días.',
                'parametros' => $rango + ['limite' => ['type' => 'integer', 'description' => 'Cantidad de productos, 1 a 10. Por defecto 5.']],
            ],
            'cobros_por_metodo' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Revisando cobros',
                'descripcion' => 'Importe cobrado por método de pago (efectivo, Yape, Plin, tarjeta...) en un rango de fechas. Solo pagos completados.',
                'parametros' => $rango,
            ],
            'comprobantes_recientes' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Buscando comprobantes',
                'descripcion' => 'Últimos comprobantes emitidos (serie-número, fecha, total, cliente, estado SUNAT). Sirve para "última factura", "último correlativo" o comprobantes de un día.',
                'parametros' => $rango + [
                    'tipo' => ['type' => 'string', 'enum' => ['factura', 'boleta', 'todos'], 'description' => 'Tipo de comprobante. Por defecto todos.'],
                    'limite' => ['type' => 'integer', 'description' => 'Cantidad, 1 a 10. Por defecto 5.'],
                ],
            ],
            'buscar_comprobante' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Buscando el comprobante',
                'descripcion' => 'Detalle de un comprobante por su número (por ejemplo B001-123 o F001-00000045): cliente, fecha, total, estado SUNAT, productos y pagos.',
                'parametros' => ['numero' => ['type' => 'string', 'description' => 'Serie y número, por ejemplo B001-123.']],
                'requeridos' => ['numero'],
            ],
            'estado_sunat' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Revisando envíos a SUNAT',
                'descripcion' => 'Cantidad de comprobantes por estado de envío a SUNAT (aceptado, pendiente, rechazado, error...) en un rango de fechas.',
                'parametros' => $rango,
            ],
            'alertas_operativas' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Revisando alertas',
                'descripcion' => 'Prioridades del día: productos sin stock, lotes que vencen en 7 días y comprobantes pendientes o con error en SUNAT.',
                'parametros' => [],
            ],
            'buscar_producto' => [
                'permiso' => 'productos.ver',
                'etiqueta' => 'Buscando el producto',
                'descripcion' => 'Busca productos activos por nombre o código de barras y devuelve su stock actual en la sucursal y lo vendido en los últimos 30 días.',
                'parametros' => ['texto' => ['type' => 'string', 'description' => 'Nombre, parte del nombre o código de barras.']],
                'requeridos' => ['texto'],
            ],
            'productos_sin_stock' => [
                'permiso' => 'inventario.ver',
                'etiqueta' => 'Revisando inventario',
                'descripcion' => 'Productos activos (no servicios) con stock cero o negativo en la sucursal.',
                'parametros' => ['limite' => ['type' => 'integer', 'description' => 'Cantidad, 1 a 20. Por defecto 10.']],
            ],
            'lotes_por_vencer' => [
                'permiso' => 'inventario.ver',
                'etiqueta' => 'Revisando vencimientos',
                'descripcion' => 'Lotes con stock que vencen dentro de los próximos N días.',
                'parametros' => ['dias' => ['type' => 'integer', 'description' => 'Ventana en días, 1 a 180. Por defecto 30.']],
            ],
            'resumen_compras' => [
                'permiso' => 'ingresos.registrar',
                'etiqueta' => 'Consultando compras',
                'descripcion' => 'Ingresos de mercadería (compras) registrados en un rango de fechas: cantidad, total y principales proveedores.',
                'parametros' => $rango,
            ],
            'estado_caja' => [
                'permiso' => 'ventas.registrar',
                'etiqueta' => 'Revisando tu caja',
                'descripcion' => 'Turno de caja abierto del usuario actual: hora de apertura, fondo inicial y lo cobrado en el turno por método.',
                'parametros' => [],
            ],
            'metodos_pago' => [
                'permiso' => 'dashboard.ver',
                'etiqueta' => 'Consultando métodos de pago',
                'descripcion' => 'Métodos de pago activos configurados, con tipo, si requieren referencia y comisión.',
                'parametros' => [],
            ],
            'buscar_cliente' => [
                'permiso' => 'clientes.ver',
                'etiqueta' => 'Buscando clientes',
                'descripcion' => 'Busca clientes por nombre o número de documento. Sin texto, devuelve el total de clientes activos.',
                'parametros' => ['texto' => ['type' => 'string', 'description' => 'Nombre, razón social o número de documento.']],
            ],
            'buscar_proveedor' => [
                'permiso' => 'proveedores.ver',
                'etiqueta' => 'Buscando proveedores',
                'descripcion' => 'Busca proveedores por razón social o RUC. Sin texto, devuelve el total de proveedores activos.',
                'parametros' => ['texto' => ['type' => 'string', 'description' => 'Razón social o RUC.']],
            ],
        ];
    }

    /** Texto breve para mostrar en el chat mientras se consulta ("Consultando ventas…"). */
    public function etiqueta(string $nombre): string
    {
        return $this->catalogo()[$nombre]['etiqueta'] ?? 'Consultando datos';
    }

    /** Definiciones en formato "tools" de la API compatible con OpenAI, solo las permitidas al usuario. */
    public function definiciones(User $usuario): array
    {
        $definiciones = [];
        foreach ($this->catalogo() as $nombre => $herramienta) {
            if (! $usuario->hasPermission($herramienta['permiso'])) continue;
            $definiciones[] = [
                'type' => 'function',
                'function' => [
                    'name' => $nombre,
                    'description' => $herramienta['descripcion'],
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) $herramienta['parametros'],
                        'required' => $herramienta['requeridos'] ?? [],
                    ],
                ],
            ];
        }

        return $definiciones;
    }

    /**
     * Ejecuta una herramienta. Nunca lanza excepciones hacia el modelo: los errores de
     * validación o permisos se devuelven como ['error' => ...] para que los explique.
     */
    public function ejecutar(string $nombre, array $argumentos, User $usuario, int $sucursalId): array
    {
        $herramienta = $this->catalogo()[$nombre] ?? null;
        if (! $herramienta) return ['error' => 'Herramienta desconocida.'];
        if (! $usuario->hasPermission($herramienta['permiso'])) return ['error' => 'El usuario no tiene permiso para esta consulta.'];

        try {
            return match ($nombre) {
                'resumen_ventas' => $this->resumenVentas($sucursalId, ...$this->rango($argumentos)),
                'comparar_ventas' => $this->compararVentas($sucursalId, $argumentos),
                'ventas_por_vendedor' => $this->ventasPorVendedor($sucursalId, ...$this->rango($argumentos)),
                'buscar_comprobante' => $this->buscarComprobante($sucursalId, $this->texto($argumentos, 'numero', true)),
                'productos_mas_vendidos' => $this->productosMasVendidos($sucursalId, $this->rango($argumentos, 30), $this->entero($argumentos, 'limite', 5, 1, 10)),
                'cobros_por_metodo' => $this->cobrosPorMetodo($sucursalId, ...$this->rango($argumentos)),
                'comprobantes_recientes' => $this->comprobantesRecientes($sucursalId, $argumentos),
                'estado_sunat' => $this->estadoSunat($sucursalId, ...$this->rango($argumentos)),
                'alertas_operativas' => $this->alertasOperativas($sucursalId),
                'buscar_producto' => $this->buscarProducto($sucursalId, $this->texto($argumentos, 'texto', true)),
                'productos_sin_stock' => $this->productosSinStock($sucursalId, $this->entero($argumentos, 'limite', 10, 1, 20)),
                'lotes_por_vencer' => $this->lotesPorVencer($sucursalId, $this->entero($argumentos, 'dias', 30, 1, 180)),
                'resumen_compras' => $this->resumenCompras($sucursalId, ...$this->rango($argumentos)),
                'estado_caja' => $this->estadoCaja($sucursalId, (int) $usuario->id_usuario),
                'metodos_pago' => $this->metodosPago(),
                'buscar_cliente' => $this->buscarEntidad('catalogos.clientes', 'nombre_razon_social', $this->texto($argumentos, 'texto')),
                'buscar_proveedor' => $this->buscarEntidad('catalogos.proveedores', 'razon_social', $this->texto($argumentos, 'texto')),
            };
        } catch (InvalidArgumentException $error) {
            return ['error' => $error->getMessage()];
        }
    }

    // ---------------------------------------------------------------- Consultas

    /** Totales con los mismos criterios del tablero (todas las ventas del período). */
    private function totalesVentas(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): object
    {
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);

        return DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)
            ->selectRaw("COUNT(*) as comprobantes, COALESCE(SUM(total_venta), 0) as total, COALESCE(AVG(total_venta), 0) as promedio, COUNT(*) FILTER (WHERE estado_sunat = 'RECHAZADO') as rechazados")
            ->first();
    }

    private function resumenVentas(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
        $base = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin);
        $resumen = $this->totalesVentas($sucursalId, $desde, $hasta);

        $datos = [
            'periodo' => $this->periodo($desde, $hasta),
            'comprobantes' => (int) $resumen->comprobantes,
            'total' => $this->dinero($resumen->total),
            'ticket_promedio' => $this->dinero($resumen->promedio),
        ];
        if ((int) $resumen->rechazados > 0) $datos['nota'] = (int) $resumen->rechazados . ' de esos comprobantes figuran rechazados por SUNAT y están incluidos en el total.';

        $dias = $desde->diffInDays($hasta) + 1;
        if ($dias > 1 && $dias <= 31) {
            $porDia = (clone $base)->get(['fecha_emision', 'total_venta'])
                ->groupBy(fn ($venta) => CarbonImmutable::parse($venta->fecha_emision)->setTimezone(self::ZONA)->toDateString())
                ->map(fn ($ventas, $fecha) => ['fecha' => CarbonImmutable::parse($fecha)->format('d/m/Y'), 'comprobantes' => $ventas->count(), 'total' => $this->dinero($ventas->sum('total_venta'))])
                ->sortKeys()->values()->all();
            $datos['por_dia'] = $porDia;
            $datos['dias_sin_ventas'] = $dias - count($porDia);
        }

        return $datos;
    }

    private function compararVentas(int $sucursalId, array $argumentos): array
    {
        [$desdeA, $hastaA] = $this->rango(['desde' => $argumentos['desde_a'] ?? null, 'hasta' => $argumentos['hasta_a'] ?? null]);
        [$desdeB, $hastaB] = $this->rango(['desde' => $argumentos['desde_b'] ?? null, 'hasta' => $argumentos['hasta_b'] ?? null]);
        $a = $this->totalesVentas($sucursalId, $desdeA, $hastaA);
        $b = $this->totalesVentas($sucursalId, $desdeB, $hastaB);
        $diferencia = (float) $a->total - (float) $b->total;

        return [
            'periodo_a' => ['periodo' => $this->periodo($desdeA, $hastaA), 'comprobantes' => (int) $a->comprobantes, 'total' => $this->dinero($a->total)],
            'periodo_b' => ['periodo' => $this->periodo($desdeB, $hastaB), 'comprobantes' => (int) $b->comprobantes, 'total' => $this->dinero($b->total)],
            'diferencia' => ($diferencia >= 0 ? '+' : '-') . $this->dinero(abs($diferencia)),
            'variacion' => (float) $b->total > 0
                ? sprintf('%+.1f %%', $diferencia / (float) $b->total * 100)
                : 'no calculable (el período B no tiene ventas)',
            'nota' => $desdeA->diffInDays($hastaA) !== $desdeB->diffInDays($hastaB) ? 'Los períodos tienen distinta cantidad de días; la comparación no es equivalente.' : null,
        ];
    }

    private function ventasPorVendedor(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
        $filas = DB::table('operaciones.ventas as venta')
            ->join('seguridad.usuarios as usuario', 'usuario.id_usuario', '=', 'venta.id_usuario')
            ->where('venta.id_sucursal', $sucursalId)->where('venta.fecha_emision', '>=', $inicio)->where('venta.fecha_emision', '<', $fin)
            ->groupBy('usuario.id_usuario', 'usuario.nombres')
            ->orderByDesc(DB::raw('SUM(venta.total_venta)'))
            ->limit(15)
            ->get(['usuario.nombres as vendedor', DB::raw('COUNT(*) as comprobantes'), DB::raw('SUM(venta.total_venta) as total')]);

        return [
            'periodo' => $this->periodo($desde, $hasta),
            'vendedores' => $filas->map(fn ($f) => ['vendedor' => $f->vendedor, 'comprobantes' => (int) $f->comprobantes, 'total' => $this->dinero($f->total)])->all(),
        ];
    }

    private function buscarComprobante(int $sucursalId, string $numero): array
    {
        if (! preg_match('/^\s*([A-Za-z0-9]{1,4})\s*-\s*0*(\d{1,10})\s*$/', $numero, $partes)) {
            throw new InvalidArgumentException('Número inválido. Usa serie y número, por ejemplo B001-123.');
        }

        $venta = DB::table('operaciones.ventas as venta')
            ->join('catalogos.tipos_comprobante as tipo', 'tipo.id_tipo_comprobante', '=', 'venta.id_tipo_comprobante')
            ->leftJoin('catalogos.clientes as cliente', 'cliente.id_cliente', '=', 'venta.id_cliente')
            ->leftJoin('seguridad.usuarios as usuario', 'usuario.id_usuario', '=', 'venta.id_usuario')
            ->where('venta.id_sucursal', $sucursalId)
            ->whereRaw('UPPER(venta.serie) = ?', [Str::upper($partes[1])])
            ->where('venta.correlativo', (int) $partes[2])
            ->first(['venta.id_venta', 'venta.serie', 'venta.correlativo', 'venta.fecha_emision', 'venta.total_venta', 'venta.igv', 'venta.estado_sunat', 'tipo.descripcion as tipo', 'cliente.nombre_razon_social as cliente', 'cliente.num_documento', 'usuario.nombres as vendedor']);

        if (! $venta) return ['numero' => Str::upper($numero), 'encontrado' => false, 'nota' => 'No existe ese comprobante en esta sucursal.'];

        $productos = DB::table('operaciones.detalle_ventas as detalle')
            ->join('inventario.precio_producto as precio', 'precio.id_precio', '=', 'detalle.id_precio')
            ->join('catalogos.productos as producto', 'producto.id_producto', '=', 'precio.id_producto')
            ->where('detalle.id_venta', $venta->id_venta)
            ->limit(30)
            ->get(['producto.nombre', 'detalle.cantidad', 'detalle.subtotal']);
        $pagos = DB::table('operaciones.pagos_ventas as pago')
            ->join('catalogos.metodos_pago as metodo', 'metodo.id_metodo', '=', 'pago.id_metodo')
            ->where('pago.id_venta', $venta->id_venta)
            ->get(['metodo.nombre as metodo', 'pago.monto', 'pago.estado_pago']);

        return [
            'encontrado' => true,
            'comprobante' => $venta->tipo . ' ' . sprintf('%s-%06d', $venta->serie, $venta->correlativo),
            'emitido' => $this->fechaHora($venta->fecha_emision),
            'cliente' => trim(($venta->cliente ?: 'Cliente varios') . ($venta->num_documento ? " ({$venta->num_documento})" : '')),
            'vendedor' => $venta->vendedor,
            'total' => $this->dinero($venta->total_venta),
            'igv' => $this->dinero($venta->igv),
            'estado_sunat' => $venta->estado_sunat,
            'productos' => $productos->map(fn ($p) => ['producto' => $p->nombre, 'cantidad' => $this->numero($p->cantidad), 'subtotal' => $this->dinero($p->subtotal)])->all(),
            'pagos' => $pagos->map(fn ($p) => ['metodo' => $p->metodo, 'monto' => $this->dinero($p->monto), 'estado' => $p->estado_pago])->all(),
        ];
    }

    private function productosMasVendidos(int $sucursalId, array $rango, int $limite): array
    {
        [$desde, $hasta] = $rango;
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
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
            ->limit($limite)
            ->get(['producto.nombre', 'unidad.nombre as unidad', DB::raw('SUM(detalle.cantidad * precio.factor_conversion) as cantidad'), DB::raw('SUM(detalle.subtotal) as total')]);

        return [
            'periodo' => $this->periodo($desde, $hasta),
            'productos' => $productos->map(fn ($p) => ['producto' => $p->nombre, 'cantidad' => $this->numero($p->cantidad), 'unidad' => $p->unidad, 'importe' => $this->dinero($p->total)])->all(),
        ];
    }

    private function cobrosPorMetodo(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
        $cobros = DB::table('operaciones.pagos_ventas as pago')
            ->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'pago.id_venta')
            ->join('catalogos.metodos_pago as metodo', 'metodo.id_metodo', '=', 'pago.id_metodo')
            ->where('venta.id_sucursal', $sucursalId)
            ->where('venta.fecha_emision', '>=', $inicio)
            ->where('venta.fecha_emision', '<', $fin)
            ->where('pago.estado_pago', 'COMPLETADO')
            ->groupBy('metodo.nombre')
            ->orderByDesc(DB::raw('SUM(pago.monto)'))
            ->get([DB::raw('metodo.nombre as metodo'), DB::raw('COUNT(*) as pagos'), DB::raw('SUM(pago.monto) as total')]);

        return [
            'periodo' => $this->periodo($desde, $hasta),
            'total' => $this->dinero($cobros->sum('total')),
            'por_metodo' => $cobros->map(fn ($c) => ['metodo' => $c->metodo, 'pagos' => (int) $c->pagos, 'total' => $this->dinero($c->total)])->all(),
        ];
    }

    private function comprobantesRecientes(int $sucursalId, array $argumentos): array
    {
        $codigos = ['factura' => '01', 'boleta' => '03'];
        $tipo = $codigos[$argumentos['tipo'] ?? 'todos'] ?? null;
        $limite = $this->entero($argumentos, 'limite', 5, 1, 10);

        $query = DB::table('operaciones.ventas as venta')
            ->join('catalogos.tipos_comprobante as tipo', 'tipo.id_tipo_comprobante', '=', 'venta.id_tipo_comprobante')
            ->leftJoin('catalogos.clientes as cliente', 'cliente.id_cliente', '=', 'venta.id_cliente')
            ->where('venta.id_sucursal', $sucursalId)
            ->when($tipo, fn ($q) => $q->where('tipo.codigo_sunat', $tipo));

        $periodo = null;
        if (! empty($argumentos['desde']) || ! empty($argumentos['hasta'])) {
            [$desde, $hasta] = $this->rango($argumentos);
            [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
            $query->where('venta.fecha_emision', '>=', $inicio)->where('venta.fecha_emision', '<', $fin);
            $periodo = $this->periodo($desde, $hasta);
        }

        $ventas = $query->orderByDesc('venta.fecha_emision')->limit($limite)
            ->get(['venta.serie', 'venta.correlativo', 'venta.fecha_emision', 'venta.total_venta', 'venta.estado_sunat', 'tipo.descripcion as tipo', 'cliente.nombre_razon_social as cliente']);

        return [
            'periodo' => $periodo ?? 'sin filtro de fecha (los más recientes)',
            'comprobantes' => $ventas->map(fn ($v) => [
                'tipo' => $v->tipo,
                'numero' => sprintf('%s-%06d', $v->serie, $v->correlativo),
                'emitido' => $this->fechaHora($v->fecha_emision),
                'total' => $this->dinero($v->total_venta),
                'cliente' => $v->cliente ?: 'Cliente varios',
                'estado_sunat' => $v->estado_sunat,
            ])->all(),
        ];
    }

    private function estadoSunat(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        [$inicio, $fin] = $this->limitesUtc($desde, $hasta);
        $estados = DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)
            ->groupBy('estado_sunat')->orderBy('estado_sunat')->selectRaw('estado_sunat, COUNT(*) as cantidad')->pluck('cantidad', 'estado_sunat');

        return ['periodo' => $this->periodo($desde, $hasta), 'por_estado' => $estados->map(fn ($cantidad) => (int) $cantidad)->all()];
    }

    private function alertasOperativas(int $sucursalId): array
    {
        $hoy = $this->hoy();
        [$inicio, $fin] = $this->limitesUtc($hoy, $hoy);

        return [
            'productos_sin_stock' => (int) (DB::selectOne($this->sqlSinStock('COUNT(*) AS total', ''), [$sucursalId])->total ?? 0),
            'lotes_que_vencen_en_7_dias' => DB::table('inventario.lotes')->where('id_sucursal', $sucursalId)->where('estado', true)->where('stock', '>', 0)
                ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $hoy->addDays(7)->toDateString()])->count(),
            'comprobantes_de_hoy_pendientes_o_con_error' => DB::table('operaciones.ventas')->where('id_sucursal', $sucursalId)->where('fecha_emision', '>=', $inicio)->where('fecha_emision', '<', $fin)
                ->whereIn('estado_sunat', ['PENDIENTE', 'ERROR', 'RECHAZADO'])->count(),
        ];
    }

    private function buscarProducto(int $sucursalId, string $texto): array
    {
        $patron = '%' . $this->escaparLike(Str::lower($texto)) . '%';
        $productos = DB::table('catalogos.productos as producto')
            ->leftJoin('catalogos.unidades_medida as unidad', 'unidad.id_unidad', '=', 'producto.id_unidad_base')
            ->where('producto.estado', true)
            ->where(fn ($q) => $q->whereRaw('LOWER(producto.nombre) LIKE ?', [$patron])->orWhere('producto.codigo_barras', 'like', $patron))
            ->orderBy('producto.nombre')
            ->limit(5)
            ->get(['producto.id_producto', 'producto.nombre', 'producto.es_servicio', 'unidad.nombre as unidad']);

        if ($productos->isEmpty()) return ['busqueda' => $texto, 'productos' => [], 'nota' => 'No hay productos activos que coincidan.'];

        $ids = $productos->pluck('id_producto')->all();
        $stock = collect(DB::select(
            'SELECT DISTINCT ON (id_producto) id_producto, stock_actual FROM inventario.kardex WHERE id_sucursal = ? AND id_producto IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id_producto, id_kardex DESC',
            [$sucursalId, ...$ids],
        ))->pluck('stock_actual', 'id_producto');

        $inicio = $this->hoy()->subDays(29)->startOfDay()->utc();
        $vendido = DB::table('operaciones.detalle_ventas as detalle')
            ->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'detalle.id_venta')
            ->join('inventario.precio_producto as precio', 'precio.id_precio', '=', 'detalle.id_precio')
            ->where('venta.id_sucursal', $sucursalId)->where('venta.fecha_emision', '>=', $inicio)->whereIn('precio.id_producto', $ids)
            ->groupBy('precio.id_producto')->selectRaw('precio.id_producto, SUM(detalle.cantidad * precio.factor_conversion) as vendido')->pluck('vendido', 'id_producto');

        return [
            'busqueda' => $texto,
            'productos' => $productos->map(fn ($p) => [
                'producto' => $p->nombre,
                'unidad' => $p->unidad,
                'stock_actual' => $p->es_servicio ? 'servicio (sin stock)' : $this->numero($stock[$p->id_producto] ?? 0) . " " . $p->unidad,
                'vendido_ultimos_30_dias' => $this->numero($vendido[$p->id_producto] ?? 0) . " " . $p->unidad,
            ])->all(),
        ];
    }

    private function productosSinStock(int $sucursalId, int $limite): array
    {
        $total = (int) (DB::selectOne($this->sqlSinStock('COUNT(*) AS total', ''), [$sucursalId])->total ?? 0);
        $productos = DB::select($this->sqlSinStock('producto.nombre', 'ORDER BY producto.nombre LIMIT ' . $limite), [$sucursalId]);

        return ['total' => $total, 'mostrados' => collect($productos)->pluck('nombre')->all()];
    }

    private function lotesPorVencer(int $sucursalId, int $dias): array
    {
        $hoy = $this->hoy();
        $lotes = DB::table('inventario.lotes as lote')
            ->join('catalogos.productos as producto', 'producto.id_producto', '=', 'lote.id_producto')
            ->where('lote.id_sucursal', $sucursalId)->where('lote.estado', true)->where('lote.stock', '>', 0)
            ->whereBetween('lote.fecha_vencimiento', [$hoy->toDateString(), $hoy->addDays($dias)->toDateString()])
            ->orderBy('lote.fecha_vencimiento')->limit(15)
            ->get(['producto.nombre', 'lote.fecha_vencimiento', 'lote.stock']);

        return [
            'ventana_dias' => $dias,
            'lotes' => $lotes->map(fn ($l) => ['producto' => $l->nombre, 'vence' => $l->fecha_vencimiento, 'stock' => $this->numero($l->stock)])->all(),
        ];
    }

    private function resumenCompras(int $sucursalId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $base = DB::table('operaciones.ingresos as ingreso')->where('ingreso.id_sucursal', $sucursalId)
            ->where('ingreso.fecha_emision', '>=', $desde->toDateString())->where('ingreso.fecha_emision', '<', $hasta->addDay()->toDateString());
        $resumen = (clone $base)->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total_ingreso), 0) as total')->first();
        $proveedores = (clone $base)->leftJoin('catalogos.proveedores as proveedor', 'proveedor.id_proveedor', '=', 'ingreso.id_proveedor')
            ->groupBy('proveedor.razon_social')->orderByDesc(DB::raw('SUM(ingreso.total_ingreso)'))->limit(5)
            ->get([DB::raw("COALESCE(proveedor.razon_social, 'Sin proveedor') as proveedor"), DB::raw('SUM(ingreso.total_ingreso) as total')]);

        return [
            'periodo' => $this->periodo($desde, $hasta),
            'ingresos' => (int) $resumen->cantidad,
            'total' => $this->dinero($resumen->total),
            'principales_proveedores' => $proveedores->map(fn ($p) => ['proveedor' => $p->proveedor, 'total' => $this->dinero($p->total)])->all(),
        ];
    }

    private function estadoCaja(int $sucursalId, int $usuarioId): array
    {
        $turno = DB::table('operaciones.turnos_caja')->where('id_sucursal', $sucursalId)->where('id_usuario', $usuarioId)->where('estado', 'ABIERTO')->latest('fecha_apertura')->first();
        if (! $turno) return ['turno_abierto' => false];

        $cobrado = DB::table('operaciones.pagos_ventas as pago')
            ->join('operaciones.ventas as venta', 'venta.id_venta', '=', 'pago.id_venta')
            ->join('catalogos.metodos_pago as metodo', 'metodo.id_metodo', '=', 'pago.id_metodo')
            ->where('venta.id_sucursal', $sucursalId)
            ->where('venta.id_turno', $turno->id_turno)->where('pago.estado_pago', 'COMPLETADO')
            ->groupBy('metodo.nombre')->get([DB::raw('metodo.nombre as metodo'), DB::raw('SUM(pago.monto) as total')]);

        return [
            'turno_abierto' => true,
            'abierto_desde' => $this->fechaHora($turno->fecha_apertura),
            'fondo_inicial' => $this->dinero($turno->monto_apertura),
            'cobrado_en_el_turno' => $cobrado->map(fn ($c) => ['metodo' => $c->metodo, 'total' => $this->dinero($c->total)])->all(),
        ];
    }

    private function metodosPago(): array
    {
        return [
            'metodos' => DB::table('catalogos.metodos_pago')->where('estado', true)->orderBy('nombre')
                ->get(['nombre', 'tipo', 'requiere_referencia', 'comision_porcentaje', 'comision_fija'])
                ->map(fn ($m) => [
                    'nombre' => $m->nombre,
                    'tipo' => $m->tipo,
                    'requiere_referencia' => (bool) $m->requiere_referencia,
                    'comision_porcentaje' => (float) $m->comision_porcentaje,
                    'comision_fija' => $this->dinero($m->comision_fija),
                ])->all(),
        ];
    }

    private function buscarEntidad(string $tabla, string $campoNombre, string $texto): array
    {
        $activos = DB::table($tabla)->where('estado', true);
        $total = (clone $activos)->count();
        if ($texto === '') return ['total_activos' => $total];

        $patron = '%' . $this->escaparLike(Str::lower($texto)) . '%';
        $coincidencias = (clone $activos)
            ->where(fn ($q) => $q->whereRaw("LOWER({$campoNombre}) LIKE ?", [$patron])->orWhere('num_documento', 'like', $patron))
            ->orderBy($campoNombre)->limit(5)->get([$campoNombre . ' as nombre', 'num_documento as documento']);

        return ['total_activos' => $total, 'busqueda' => $texto, 'coincidencias' => $coincidencias->map(fn ($e) => (array) $e)->all()];
    }

    // ---------------------------------------------------------------- Utilidades

    private function sqlSinStock(string $columnas, string $sufijo): string
    {
        return "WITH ultimo_kardex AS (SELECT DISTINCT ON (id_producto) id_producto, stock_actual FROM inventario.kardex WHERE id_sucursal = ? ORDER BY id_producto, id_kardex DESC) "
            . "SELECT {$columnas} FROM catalogos.productos producto LEFT JOIN ultimo_kardex kardex ON kardex.id_producto = producto.id_producto "
            . "WHERE producto.estado = TRUE AND producto.es_servicio = FALSE AND COALESCE(kardex.stock_actual, 0) <= 0 {$sufijo}";
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} Fechas locales (inicio de día), ambas inclusive. */
    private function rango(array $argumentos, int $diasPorDefecto = 1): array
    {
        $hoy = $this->hoy();
        $desde = $this->fecha($argumentos['desde'] ?? null);
        $hasta = $this->fecha($argumentos['hasta'] ?? null);

        if (! $desde && ! $hasta) [$desde, $hasta] = [$hoy->subDays($diasPorDefecto - 1), $hoy];
        $desde ??= $hasta;
        $hasta ??= $desde;
        if ($hasta->lessThan($desde)) [$desde, $hasta] = [$hasta, $desde];
        if ($desde->diffInDays($hasta) + 1 > self::MAX_DIAS_RANGO) throw new InvalidArgumentException('El rango máximo es de un año.');

        return [$desde, $hasta];
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || trim($valor) === '') return null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) || ! checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
            throw new InvalidArgumentException("Fecha inválida: {$valor}. Usa YYYY-MM-DD.");
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $valor, self::ZONA)->startOfDay();
    }

    /** Convierte días locales inclusive a límites UTC [inicio, fin). */
    private function limitesUtc(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return [$desde->startOfDay()->utc(), $hasta->addDay()->startOfDay()->utc()];
    }

    private function entero(array $argumentos, string $clave, int $defecto, int $minimo, int $maximo): int
    {
        $valor = $argumentos[$clave] ?? $defecto;
        return is_numeric($valor) ? max($minimo, min($maximo, (int) $valor)) : $defecto;
    }

    private function texto(array $argumentos, string $clave, bool $requerido = false): string
    {
        $valor = trim(Str::limit((string) ($argumentos[$clave] ?? ''), 80, ''));
        if ($requerido && $valor === '') throw new InvalidArgumentException("Falta el parámetro {$clave}.");
        return $valor;
    }

    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }

    private function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA)->startOfDay();
    }

    private function periodo(CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        return $desde->equalTo($hasta) ? $desde->format('d/m/Y') : $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
    }

    private function fechaHora(mixed $valor): string
    {
        return CarbonImmutable::parse($valor)->setTimezone(self::ZONA)->format('d/m/Y H:i');
    }

    /** Monto listo para mostrar: el modelo lo copia tal cual y no tiene que formatear ni redondear. */
    private function dinero(mixed $valor): string
    {
        return 'S/ ' . number_format((float) $valor, 2, '.', ',');
    }

    /** Cantidad sin ceros sobrantes: 12, 2.5, 0.75. */
    private function numero(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ','), '0'), '.');
    }
}
