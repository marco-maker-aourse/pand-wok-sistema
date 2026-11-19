<?php

namespace App\Services\Impresion;

use App\Models\TerminalPos;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Models\Venta;
use App\Services\Comprobantes\PresentarComprobanteService;
use Illuminate\Support\Str;

/**
 * Persistir primero evita que una caída de red deje una venta cobrada sin trazabilidad de impresión.
 * Este servicio no transmite datos a la red ni modifica la venta, el stock o SUNAT.
 */
class CrearTrabajoImpresionService
{
    public const MODO_AUTOMATICA = 'AUTOMATICA';

    public const MODO_MANUAL = 'MANUAL';

    /** La venta no tiene terminal activa con impresora activa. */
    public const MODO_NO_DISPONIBLE = 'NO_DISPONIBLE';

    public function __construct(private readonly PresentarComprobanteService $presentacion) {}

    /**
     * Encola la representación fiscal después de una aceptación SUNAT ya persistida.
     * La impresora es asíncrona: su indisponibilidad jamás invalida la venta ni el CPE.
     */
    public function comprobanteFiscalAceptado(Venta $venta): ?TrabajoImpresion
    {
        if ($venta->estado_sunat !== 'ACEPTADO') {
            return null;
        }

        return $this->crearTrabajoFiscal($venta, "venta:{$venta->id_venta}:comprobante-fiscal");
    }

    /**
     * La reimpresión genera un nuevo trabajo, pero nunca reemite el CPE ni la venta.
     * `$solicitud` identifica cada pulsación de «Imprimir»: si la misma solicitud llega
     * dos veces (doble envío, reintento de red) la clave única devuelve el mismo trabajo.
     */
    public function reimprimirComprobanteFiscal(Venta $venta, ?string $solicitud = null): ?TrabajoImpresion
    {
        if ($venta->estado_sunat !== 'ACEPTADO') {
            return null;
        }

        return $this->crearTrabajoFiscal($venta, "venta:{$venta->id_venta}:reimpresion:".($solicitud ?? Str::uuid()));
    }

    /**
     * Cómo se imprime esta venta según su terminal. La interfaz lo usa para decidir si
     * muestra «Imprimir ticket»: solo con comprobante aceptado y sin impresión automática
     * ya encolada. Usa las relaciones ya cargadas de la venta.
     *
     * @return array{modo: string, puede_imprimir: bool, encolada: bool}
     */
    public function estadoImpresion(Venta $venta, bool $encolada): array
    {
        $terminal = $venta->turno?->terminal;
        $aceptado = $venta->estado_sunat === 'ACEPTADO';

        if (! $terminal || ! $terminal->activo || ! $terminal->impresora?->activo) {
            return ['modo' => self::MODO_NO_DISPONIBLE, 'puede_imprimir' => false, 'encolada' => false];
        }

        $automatica = (bool) $terminal->impresion_automatica;

        return [
            'modo' => $automatica ? self::MODO_AUTOMATICA : self::MODO_MANUAL,
            'puede_imprimir' => $aceptado,
            'encolada' => $automatica && $aceptado && $encolada,
        ];
    }

    /** Ancho de papel (58 u 80 mm) de la impresora de la caja de la venta; 80 mm si no tiene. */
    public function anchoPapel(Venta $venta): int
    {
        return (int) $venta->turno?->terminal?->impresora?->ancho_papel_mm === 58 ? 58 : 80;
    }

    private function crearTrabajoFiscal(Venta $venta, string $claveIdempotencia): ?TrabajoImpresion
    {
        $venta->loadMissing(['turno.terminal.impresora', 'cliente', 'detalles.precio.producto', 'detalles.precio.presentacion']);
        $terminal = $venta->turno?->terminal;

        if (! $terminal || ! $terminal->activo || ! $terminal->impresora?->activo) {
            return null;
        }

        // La impresión automática respeta la política configurada por la terminal.
        if (str_contains($claveIdempotencia, ':comprobante-fiscal') && ! $terminal->impresion_automatica) {
            return null;
        }

        return TrabajoImpresion::withoutGlobalScopes()->firstOrCreate(
            ['clave_idempotencia' => $claveIdempotencia],
            [
                'id_sucursal' => $venta->id_sucursal,
                'id_terminal' => $terminal->id_terminal,
                'id_venta' => $venta->id_venta,
                // El esquema vigente usa este tipo para representaciones de venta.
                // El contenido adjunto es fiscal y no depende de datos del navegador.
                'tipo' => 'TICKET_VENTA',
                'estado' => 'PENDIENTE',
                'contenido' => $this->ticket($venta, $terminal),
            ],
        );
    }

    /** Diagnóstico y prueba se resuelven por el mismo agente de la sucursal, nunca desde Laravel. */
    public function prueba(Impresora $impresora, string $tipo): ?TrabajoImpresion
    {
        $terminal = TerminalPos::query()
            ->where('id_sucursal', $impresora->id_sucursal)
            ->where('id_impresora', $impresora->id_impresora)
            ->where('activo', true)
            ->orderBy('id_terminal')
            ->first();

        if (! $terminal) {
            return null;
        }

        return TrabajoImpresion::withoutGlobalScopes()->create([
            'id_sucursal' => $impresora->id_sucursal,
            'id_terminal' => $terminal->id_terminal,
            'tipo' => $tipo,
            'estado' => 'PENDIENTE',
            'clave_idempotencia' => "{$tipo}:{$impresora->id_impresora}:".Str::uuid(),
            'contenido' => [
                'version' => 1,
                'impresora' => [
                    'id' => $impresora->id_impresora,
                    'conexion' => $impresora->tipo_conexion,
                    'identificador' => $impresora->identificador,
                    'puerto' => $impresora->puerto,
                    'ancho_papel_mm' => $impresora->ancho_papel_mm,
                    'escpos' => $impresora->soporta_escpos,
                ],
            ],
        ]);
    }

    private function ticket(Venta $venta, TerminalPos $terminal): array
    {
        $impresora = $terminal->impresora;
        $comprobante = $this->presentacion->paraVenta($venta);

        return [
            // v2 agrega datos para la representación impresa completa (unidad, letras, cajero...).
            // Solo añade campos: un agente anterior sigue imprimiendo con los que conoce.
            'version' => 2,
            // Mismo contrato fiscal que el PDF; el agente adapta solo el ancho físico (58/80 mm).
            'comprobante' => $comprobante,
            'representacion_fiscal' => true,
            'estado_sunat' => $comprobante['estado_sunat'],
            'qr_fiscal' => $comprobante['qr_fiscal'],
            'documento' => $comprobante['documento']['numero'],
            'titulo' => $comprobante['documento']['titulo'],
            'emitido_en' => $comprobante['documento']['emitido_en'],
            'cliente' => $comprobante['cliente']['nombre'],
            'documento_cliente' => $comprobante['cliente']['tipo_documento'].': '.$comprobante['cliente']['numero_documento'],
            'total' => (string) $comprobante['totales']['total'],
            'moneda' => 'PEN',
            'copias' => $terminal->copias_ticket,
            'impresora' => [
                'id' => $impresora->id_impresora,
                'conexion' => $impresora->tipo_conexion,
                'identificador' => $impresora->identificador,
                'puerto' => $impresora->puerto,
                'ancho_papel_mm' => $impresora->ancho_papel_mm,
                'escpos' => $impresora->soporta_escpos,
                'corte_automatico' => $impresora->corte_automatico,
                'abre_cajon' => $impresora->abre_cajon,
            ],
            'items' => collect($comprobante['items'])->map(fn (array $detalle): array => [
                'nombre' => $detalle['nombre'], 'presentacion' => $detalle['presentacion'],
                'unidad' => $detalle['unidad'], 'codigo' => $detalle['codigo'],
                'cantidad' => (string) $detalle['cantidad'], 'precio_unitario' => (string) $detalle['precio_unitario'],
                'subtotal' => (string) $detalle['subtotal'],
            ])->all(),
        ];
    }
}
