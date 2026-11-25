<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Impresora;
use App\Models\TerminalPos;
use App\Models\TrabajoImpresion;
use App\Services\Impresion\GestionarVinculacionAgenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgenteImpresionController extends Controller
{
    /** Resultado que informa el agente cuando no pudo conectar con la impresora (no salió papel). */
    private const SIN_CONEXION = 'SIN_CONEXION';
    /** Pausa antes de volver a ofrecer un trabajo cuya impresora no respondió. */
    private const ESPERA_REINTENTO_S = 20;

    public function vincular(Request $request, GestionarVinculacionAgenteService $vinculacion): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'digits:6'],
            'identificador_agente' => ['required', 'string', 'max:150'],
            'version' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $vinculacion->confirmar($data['codigo'], $data['identificador_agente'], $data['version'] ?? null);
        /** @var TerminalPos $terminal */
        $terminal = $result['terminal'];
        $ancho = $this->anchoPapel($terminal);

        return response()->json([
            // Se devuelve una sola vez; el agente debe protegerlo con el almacén seguro del SO.
            'token' => $result['token'],
            'terminal' => ['id' => $terminal->id_terminal, 'codigo' => $terminal->codigo, 'nombre' => $terminal->nombre],
            // El agente adopta este ancho al vincularse en lugar de su valor por defecto (58 mm).
            'impresora' => $ancho ? ['ancho_papel_mm' => $ancho] : null,
        ], 201);
    }

    public function siguiente(Request $request): JsonResponse
    {
        /** @var TerminalPos $terminal */
        $terminal = $request->attributes->get('terminal_pos');
        $this->registrarContacto($terminal, $request->string('version')->toString());

        $trabajo = DB::transaction(function () use ($terminal): ?TrabajoImpresion {
            // No se reintenta automáticamente lo entregado: una térmica común no puede
            // demostrar si ya imprimió y reenviar podría duplicar un comprobante.
            TrabajoImpresion::withoutGlobalScopes()
                ->where('id_terminal', $terminal->id_terminal)
                ->where('estado', 'ENTREGADO_AGENTE')
                ->where('reclamado_en', '<', now()->subMinutes(2))
                ->update(['estado' => 'REQUIERE_REVISION', 'detalle_error' => 'El agente no confirmó la recepción dentro del tiempo previsto. No se reintentó para evitar un duplicado físico.', 'procesado_en' => now()]);

            TrabajoImpresion::vencerPendientes(TrabajoImpresion::withoutGlobalScopes()->where('id_terminal', $terminal->id_terminal));

            $job = TrabajoImpresion::withoutGlobalScopes()
                ->where('id_terminal', $terminal->id_terminal)
                ->where('estado', 'PENDIENTE')
                ->where('disponible_desde', '<=', now())
                ->orderBy('id_trabajo_impresion')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if (! $job) {
                return null;
            }

            $job->update([
                'estado' => 'ENTREGADO_AGENTE',
                'reclamado_en' => now(),
                'intentos' => $job->intentos + 1,
                'detalle_error' => null,
            ]);

            return $job->fresh();
        });

        // El agente adopta en cada sondeo el ancho vigente de la impresora de su terminal.
        return response()->json([
            'data' => $trabajo ? $this->jobPayload($trabajo) : null,
            'terminal' => ['ancho_papel_mm' => $this->anchoPapel($terminal)],
        ]);
    }

    public function resultado(Request $request, int $trabajo): JsonResponse
    {
        /** @var TerminalPos $terminal */
        $terminal = $request->attributes->get('terminal_pos');
        $data = $request->validate([
            'estado' => ['required', Rule::in(['ENVIADO_SO', 'CONFIRMADO', 'FALLIDO'])],
            'detalle_error' => ['nullable', 'string', 'max:1000'],
            'resultado_dispositivo' => ['nullable', 'string', 'max:30'],
        ]);

        return DB::transaction(function () use ($trabajo, $terminal, $data, $request): JsonResponse {
            $job = TrabajoImpresion::withoutGlobalScopes()
                ->whereKey($trabajo)
                ->where('id_terminal', $terminal->id_terminal)
                ->lockForUpdate()->firstOrFail();

            // La red puede perder la respuesta después de persistir el resultado. Repetir la
            // misma confirmación es seguro; una transición distinta se rechaza explícitamente.
            if (in_array($job->estado, ['ENVIADO_SO', 'CONFIRMADO', 'FALLIDO'], true)) {
                abort_unless($job->estado === $data['estado'], 409, 'El trabajo ya fue cerrado con otro resultado.');

                return response()->json(['data' => ['id_trabajo_impresion' => $job->id_trabajo_impresion, 'estado' => $job->estado, 'idempotente' => true]]);
            }

            abort_unless($job->estado === 'ENTREGADO_AGENTE', 409, 'El trabajo no está disponible para confirmación.');

            // El agente no logró conectar con la impresora (apagada, lejos, sin Bluetooth): no
            // salió papel, así que el trabajo vuelve a la cola y sale solo al reconectarla. Si
            // pasa la vigencia sin imprimirse, se cancela al consultar la cola.
            if ($data['estado'] === 'FALLIDO' && ($data['resultado_dispositivo'] ?? null) === self::SIN_CONEXION) {
                $job->update([
                    'estado' => 'PENDIENTE',
                    'disponible_desde' => now()->addSeconds(self::ESPERA_REINTENTO_S),
                    'reclamado_en' => null,
                    'detalle_error' => 'Esperando a la impresora: '.($data['detalle_error'] ?? 'no se pudo conectar.'),
                    'resultado_dispositivo' => self::SIN_CONEXION,
                ]);
                Impresora::withoutGlobalScopes()->where('id_sucursal', $job->id_sucursal)
                    ->whereKey(data_get($job->contenido, 'impresora.id'))
                    ->update(['estado_conexion' => 'desconectada', 'ultima_verificacion' => now(), 'ultimo_error_conexion' => $data['detalle_error'] ?? null]);

                return response()->json(['data' => ['id_trabajo_impresion' => $job->id_trabajo_impresion, 'estado' => 'PENDIENTE', 'reintento' => true]]);
            }

            // CONFIRMADO se reserva a hardware capaz de reportarlo. ESC/POS común solo llega a ENVIADO_SO.
            $job->update([
                'estado' => $data['estado'],
                'detalle_error' => $data['estado'] === 'FALLIDO' ? ($data['detalle_error'] ?? 'El agente reportó un fallo de impresión.') : null,
                'resultado_dispositivo' => $data['resultado_dispositivo'] ?? null,
                'procesado_en' => now(),
            ]);

            $impresora = $job->tipo === 'COMANDA'
                ? Impresora::withoutGlobalScopes()->where('id_sucursal', $job->id_sucursal)->find(data_get($job->contenido, 'impresora.id'))
                : $terminal->impresora;
            if ($impresora && $job->tipo === 'DIAGNOSTICO') {
                $estadoConexion = in_array($data['resultado_dispositivo'] ?? null, ['conectada', 'desconectada', 'error'], true)
                    ? $data['resultado_dispositivo']
                    : ($data['estado'] === 'FALLIDO' ? 'error' : 'desconocido');
                $impresora->update([
                    'estado_conexion' => $estadoConexion,
                    'ultima_verificacion' => now(),
                    'ultimo_error_conexion' => $data['estado'] === 'FALLIDO' ? ($data['detalle_error'] ?? null) : null,
                ]);
            } elseif ($impresora) {
                $impresora->update([
                    'ultima_impresion' => now(),
                    'ultimo_resultado_impresion' => match ($data['estado']) {
                        'CONFIRMADO' => 'correcta', 'FALLIDO' => 'fallida', default => 'desconocida',
                    },
                    'ultimo_error_impresion' => $data['estado'] === 'FALLIDO' ? ($data['detalle_error'] ?? null) : null,
                ]);
            }
            $this->registrarContacto($terminal, $request->string('version')->toString());

            return response()->json(['data' => ['id_trabajo_impresion' => $job->id_trabajo_impresion, 'estado' => $data['estado']]]);
        }, 3);
    }

    /** Sincroniza únicamente el nombre de cola detectado por su propio agente local. */
    public function configurarImpresoraSistema(Request $request): JsonResponse
    {
        /** @var TerminalPos $terminal */
        $terminal = $request->attributes->get('terminal_pos');
        $data = $request->validate([
            'nombre' => ['nullable', 'string', 'max:255'],
            'ancho_papel_mm' => ['nullable', 'integer', Rule::in([58, 80])],
        ]);

        $terminal->update(['impresora_sistema_nombre' => $data['nombre'] ?? null]);

        // El agente solo puede actualizar la impresora que ya pertenece a su terminal.
        // No acepta una impresora o una sucursal enviada desde el equipo local.
        if (isset($data['ancho_papel_mm']) && $terminal->impresora) {
            $terminal->impresora->update(['ancho_papel_mm' => $data['ancho_papel_mm']]);
        }

        return response()->json(['data' => [
            'impresora_sistema_nombre' => $terminal->impresora_sistema_nombre,
            'ancho_papel_mm' => $terminal->impresora?->ancho_papel_mm,
        ]]);
    }

    /** Sin sesión no hay sucursal activa: la impresora se acota explícitamente a la de la terminal. */
    private function anchoPapel(TerminalPos $terminal): ?int
    {
        if (! $terminal->id_impresora) {
            return null;
        }
        $ancho = Impresora::withoutGlobalScopes()
            ->where('id_sucursal', $terminal->id_sucursal)
            ->whereKey($terminal->id_impresora)
            ->value('ancho_papel_mm');

        return in_array((int) $ancho, [58, 80], true) ? (int) $ancho : null;
    }

    private function jobPayload(TrabajoImpresion $job): array
    {
        return ['id' => $job->id_trabajo_impresion, 'tipo' => $job->tipo, 'intentos' => $job->intentos, 'contenido' => $job->contenido];
    }

    private function registrarContacto(TerminalPos $terminal, string $version): void
    {
        // Evita una escritura por cada sondeo de pocos segundos.
        if (! $terminal->ultimo_contacto_en || $terminal->ultimo_contacto_en->lt(now()->subMinute()) || ($version !== '' && $version !== $terminal->version_agente)) {
            $terminal->update(['ultimo_contacto_en' => now(), 'version_agente' => $version !== '' ? $version : $terminal->version_agente]);
        }
    }
}
