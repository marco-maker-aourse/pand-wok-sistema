<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ProductoPresentacion;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Exceptions\DocumentLookupException;
use App\Jobs\EmitirComprobanteSunatJob;
use App\Services\External\DocumentLookupService;
use App\Http\Requests\Operaciones\StoreVentaRequest;
use App\Services\Ventas\CatalogoPosService;
use App\Services\Ventas\PresentarVentaService;
use App\Services\Ventas\RegistrarVentaService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\Ventas\DiagnosticoSunatService;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    public function index(Request $request, CatalogoPosService $catalogo, PresentarVentaService $presentar, DiagnosticoSunatService $diagnostico): Response
    {
        $user = $request->user();
        $estadoSunat = strtoupper((string) $request->query('estado_sunat', ''));
        $estadosPermitidos = ['PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ERROR', 'REGISTRADO', 'NO_APLICA'];
        $filtraEstado = in_array($estadoSunat, $estadosPermitidos, true);
        // Tipo de comprobante → código SUNAT (NV = nota de venta, documento interno).
        $codigosTipo = ['BOLETA' => '03', 'FACTURA' => '01', 'NOTA_VENTA' => Venta::NOTA_VENTA];
        $tipo = strtoupper((string) $request->query('tipo', ''));
        $codigoTipo = $codigosTipo[$tipo] ?? null;

        $ventas = Venta::query()
            ->with([
                ...PresentarVentaService::RELACIONES,
                'pagos:id_pago,id_venta,id_metodo,monto,monto_recibido,vuelto,comision_pasarela,estado_pago',
            ])
            ->where('id_sucursal', $user->idSucursalActiva())
            ->when($filtraEstado, fn ($query) => $query->where('estado_sunat', $estadoSunat))
            ->when($codigoTipo, fn ($query) => $query->whereHas('comprobante', fn ($q) => $q->where('codigo_sunat', $codigoTipo)))
            ->latest('id_venta')
            ->select([
                'id_venta', 'id_sucursal', 'id_cliente', 'id_turno', 'id_tipo_comprobante',
                'serie', 'correlativo', 'fecha_emision', 'op_gravadas', 'op_exoneradas',
                'igv', 'descuento_global', 'total_venta', 'estado_sunat', 'hash_cpe',
                'enlace_xml', 'enlace_cdr', 'enlace_pdf',
                'mensaje_sunat', 'codigo_respuesta_sunat', 'intentos_sunat', 'ultimo_intento_sunat',
            ])
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Venta $venta): array => $presentar->paraHistorial($venta));

        return Inertia::render('Ventas/Index', [
            'ventas' => $ventas,
            'estadoSunat' => $filtraEstado ? $estadoSunat : 'TODOS',
            'tipoComprobante' => $codigoTipo ? $tipo : 'TODOS',
            'datosEmisor' => $catalogo->datosEmisor($user->idSucursalActiva()),
            'turnoAbierto' => $catalogo->turnoAbierto($user->id_usuario, $user->idSucursalActiva()),
            'terminales' => $catalogo->terminalesActivas($user->idSucursalActiva()),
            // Qué falta para que SUNAT acepte (sin exponer claves); se calcula al abrir la pantalla.
            'diagnosticoSunat' => fn () => $diagnostico->revisar($user->idSucursalActiva()),
            'pendientesSunat' => fn () => Venta::query()->where('id_sucursal', $user->idSucursalActiva())->where('estado_sunat', 'PENDIENTE')->count(),
        ]);
    }

    /** Endpoint para autocompletar catálogo: PostgreSQL ILIKE, 30 resultados como máximo. */
    public function buscarProductos(Request $request, CatalogoPosService $catalogo): JsonResponse
    {
        $term = trim((string) $request->string('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $catalogo->productos($request->user()->idSucursalActiva(), $term, 30),
        ]);
    }

    /**
     * Clientes ya registrados por nombre o documento: el cajero rara vez recuerda
     * el DNI. La consulta externa (SUNAT/RENIEC) sigue siendo solo por documento.
     */
    public function buscarClientes(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json([
            'data' => Cliente::query()
                ->where('estado', true)
                ->buscar($data['q'])
                ->orderBy('nombre_razon_social')
                ->limit(15)
                ->get(['id_cliente', 'id_tipo_documento', 'num_documento', 'nombre_razon_social', 'direccion_fiscal', 'email', 'telefono']),
        ]);
    }

    /** Consulta externa opcional; el tipo y la longitud proceden del catálogo activo. */
    public function buscarYGuardarCliente(Request $request, DocumentLookupService $documentos): JsonResponse
    {
        $data = $request->validate([
            'id_tipo_documento' => [
                'required',
                'integer',
                Rule::exists(TipoDocumento::class, 'id_tipo_documento')->where('estado', true),
            ],
            'documento' => ['required', 'string', 'max:15', 'regex:/^\\d+$/'],
        ]);
        $documentType = TipoDocumento::query()
            ->whereKey($data['id_tipo_documento'])
            ->where('estado', true)
            ->firstOrFail();

        if (! in_array((string) $documentType->codigo_sunat, ['1', '6'], true)) {
            return response()->json(['message' => 'Solo se pueden consultar DNI o RUC desde el POS.'], 422);
        }

        $documento = $data['documento'];
        $longitudExacta = $documentType->longitud_exacta;

        if ($longitudExacta === null || strlen($documento) !== $longitudExacta) {
            return response()->json([
                'message' => $longitudExacta === null
                    ? 'El tipo de documento no tiene una longitud configurada.'
                    : "{$documentType->nombre} debe tener {$longitudExacta} dígitos.",
            ], 422);
        }

        if ($cliente = Cliente::query()->where('num_documento', $documento)->first()) {
            if (! $cliente->estado) {
                return response()->json(['message' => 'El cliente asociado a este documento está inactivo.'], 422);
            }

            return response()->json(['success' => true, 'cliente' => $cliente, 'origen' => 'local']);
        }

        try {
            $isRuc = (string) $documentType->codigo_sunat === TipoDocumento::RUC;
            $result = $documentos->lookup($isRuc ? 'ruc' : 'dni', $documento);
            $cliente = Cliente::query()->create([
                'id_tipo_documento' => $documentType->id_tipo_documento,
                'num_documento' => $documento,
                'nombre_razon_social' => strtoupper($result['nombre']),
                'direccion_fiscal' => $result['direccion'],
                'estado' => true,
            ]);

            return response()->json(['success' => true, 'cliente' => $cliente, 'origen' => 'consulta_documental']);
        } catch (DocumentLookupException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->status);
        }
    }

    /** El request del POS se transforma a un comando interno; el servidor ignora precio y subtotal enviados. */
    public function store(StoreVentaRequest $request, RegistrarVentaService $service, PresentarVentaService $presentar): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $turn = TurnoCaja::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('id_sucursal', $user->idSucursalActiva())
            ->where('estado', 'ABIERTO')
            ->latest('id_turno')
            ->first();

        if (! $turn) {
            throw ValidationException::withMessages(['venta' => 'Debes abrir un turno de caja antes de registrar ventas.']);
        }

        $receipt = TipoComprobante::query()
            ->where('codigo_sunat', $data['tipo_comprobante'] === 'FACTURA' ? TipoComprobante::FACTURA : TipoComprobante::BOLETA)
            ->where('estado', true)
            ->firstOrFail();

        $details = collect($data['detalles'])
            ->map(fn (array $detail): array => ['id_precio' => $detail['id_prod_pres'], 'cantidad' => $detail['cantidad']])
            ->all();

        try {
            $sale = $service->execute([
                'id_sucursal' => $user->idSucursalActiva(),
                'id_usuario' => $user->id_usuario,
                'id_turno' => $turn->id_turno,
                'id_cliente' => $data['id_cliente'],
                'id_tipo_comprobante' => $receipt->id_tipo_comprobante,
                'detalles' => $details,
                'pagos' => $data['pagos'] ?? [[
                    'id_metodo' => $data['id_metodo_pago'],
                    'nro_referencia' => $data['nro_referencia'] ?? null,
                    'monto_recibido' => $data['monto_recibido'] ?? null,
                ]],
            ]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['venta' => $exception->getMessage()]);
        }

        $sale->load(PresentarVentaService::RELACIONES);

        // La venta ya fue confirmada dentro de su transacción. En el entorno
        // actual no existe un worker ni tabla `jobs`; ejecutar al terminar la
        // respuesta evita que la cola o SUNAT conviertan una venta válida en 500.
        // El servicio persiste cualquier emisión no completada como PENDIENTE.
        EmitirComprobanteSunatJob::dispatchAfterResponse($sale->id_venta);

        return response()->json($presentar->paraRegistro($sale));
    }
}
