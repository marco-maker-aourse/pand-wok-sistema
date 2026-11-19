<?php

namespace App\Http\Controllers\Integraciones;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Asistente\AgenteAsistente;
use App\Services\Asistente\ServicioAsistenteOperativo;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IAController extends Controller
{
    public function __construct(
        private readonly AgenteAsistente $agente,
        private readonly ServicioAsistenteOperativo $respaldo,
    ) {
    }

    /** Respuesta completa en JSON (integraciones y compatibilidad). */
    public function preguntar(Request $request): JsonResponse
    {
        [$mensaje, $historial] = $this->validar($request);
        $usuario = $request->user();

        $resultado = $this->resolver($mensaje, $historial, $usuario, $usuario->idSucursalActiva(), $request->session());

        return response()->json(['success' => $resultado['ok'], 'respuesta' => $resultado['texto'], 'origen' => $resultado['origen']], $resultado['ok'] ? 200 : 503);
    }

    /**
     * Chat con streaming: NDJSON (un objeto JSON por línea) con eventos
     * estado | texto | reiniciar | fin | error. El navegador muestra el texto a medida que llega.
     */
    public function chat(Request $request): StreamedResponse
    {
        [$mensaje, $historial] = $this->validar($request);
        $usuario = $request->user();
        // Se resuelve antes de abrir el stream: si falla, Laravel responde con su error normal.
        $sucursalId = $usuario->idSucursalActiva();
        $sesion = $request->session();

        return response()->stream(function () use ($mensaje, $historial, $usuario, $sucursalId, $sesion) {
            // Sin buffers intermedios de PHP, cada evento sale al navegador en cuanto se genera.
            // Algunos buffers (del hosting o extensiones) no se pueden cerrar: se deja de intentar.
            while (ob_get_level() > 0 && @ob_end_flush());
            @ini_set('zlib.output_compression', '0');

            $emitir = static function (array $evento): void {
                echo json_encode($evento, JSON_UNESCAPED_UNICODE) . "\n";
                flush();
            };

            $resultado = $this->resolver($mensaje, $historial, $usuario, $sucursalId, $sesion, $emitir);
            $emitir($resultado['ok']
                ? ['tipo' => 'fin', 'texto' => $resultado['texto'], 'origen' => $resultado['origen']]
                : ['tipo' => 'error', 'texto' => $resultado['texto']]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @return array{0: string, 1: array<int, array{rol: string, texto: string}>} */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'max:500'],
            // Historial reciente que envía el navegador para entender seguimientos. Es solo texto:
            // la sucursal y los permisos salen siempre de la sesión.
            'historial' => ['sometimes', 'array', 'max:12'],
            'historial.*.rol' => ['required', 'in:user,bot'],
            'historial.*.texto' => ['required', 'string', 'max:4000'],
        ]);

        return [trim($datos['mensaje']), $datos['historial'] ?? []];
    }

    /**
     * IA con herramientas → si falla o no hay proveedor, respuestas por palabras clave.
     *
     * @return array{ok: bool, texto: string, origen: string}
     */
    private function resolver(string $mensaje, array $historial, User $usuario, int $sucursalId, Session $sesion, ?callable $emitir = null): array
    {
        $huboTexto = false;
        if ($this->agente->disponible()) {
            try {
                $texto = $this->agente->responder($mensaje, $historial, $usuario, $sucursalId, $emitir ? function (array $evento) use ($emitir, &$huboTexto) {
                    if ($evento['tipo'] === 'texto') $huboTexto = true;
                    if ($evento['tipo'] === 'reiniciar') $huboTexto = false;
                    $emitir($evento);
                } : null);

                return ['ok' => true, 'texto' => $texto, 'origen' => 'ia'];
            } catch (\Throwable $exception) {
                report($exception);
                // Si ya se mostró texto parcial, el respaldo lo reemplaza por completo.
                if ($emitir && $huboTexto) $emitir(['tipo' => 'reiniciar']);
            }
        }

        $respuesta = $this->respaldo->resolverConsultaOperativa($mensaje, $sucursalId, (int) $usuario->id_usuario, $sesion);
        if ($respuesta) return ['ok' => true, 'texto' => $respuesta, 'origen' => 'respaldo'];

        return [
            'ok' => false,
            'origen' => 'respaldo',
            'texto' => $this->agente->disponible()
                ? 'El servicio de consultas está ocupado en este momento. Inténtalo en unos segundos o prueba con una pregunta directa, por ejemplo “ventas de hoy” o “stock de arroz”.'
                : 'No reconocí la consulta. Prueba con “ventas de hoy”, “stock de arroz”, “estado de caja” o “productos por vencer”.',
        ];
    }
}
