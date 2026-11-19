<?php

namespace App\Services\Asistente\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

/**
 * Cliente de "chat completions" compatible con OpenAI con varios proveedores en cadena.
 *
 * - Prueba los proveedores en el orden de config('asistente.proveedores') y pasa al
 *   siguiente ante límite de uso, caída, tiempo agotado o configuración inválida.
 * - Con streaming, el texto se entrega a $alTexto a medida que llega. Una vez que un
 *   proveedor empezó a emitir texto ya no se cambia de proveedor (no se mezclan respuestas).
 */
class ClienteLlm
{
    /** @return list<ProveedorLlm> */
    public function proveedores(): array
    {
        $conexiones = config('asistente.conexiones', []);
        $lista = [];
        foreach (config('asistente.proveedores', []) as $nombre) {
            $proveedor = isset($conexiones[$nombre]) ? ProveedorLlm::desdeConfig($nombre, $conexiones[$nombre]) : null;
            if ($proveedor) $lista[] = $proveedor;
        }

        return $lista;
    }

    public function disponible(): bool
    {
        return $this->proveedores() !== [];
    }

    /**
     * @param  list<array<string, mixed>>  $mensajes
     * @param  list<array<string, mixed>>  $herramientas
     * @param  null|callable(string): void  $alTexto  Recibe fragmentos de texto (solo con streaming).
     */
    public function completar(array $mensajes, array $herramientas = [], ?callable $alTexto = null): RespuestaLlm
    {
        $proveedores = $this->proveedores();
        if ($proveedores === []) throw new ErrorProveedorLlm('No hay proveedores de IA configurados.');

        $ultimoError = null;
        foreach ($proveedores as $proveedor) {
            // Un proveedor que acaba de dar límite de uso se salta un rato (compartido entre peticiones).
            if (Cache::has($this->clavePausa($proveedor))) continue;

            $emitio = false;
            $avisar = $alTexto ? function (string $fragmento) use ($alTexto, &$emitio) {
                $emitio = true;
                $alTexto($fragmento);
            } : null;

            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    return $this->llamar($proveedor, $mensajes, $herramientas, $avisar);
                } catch (ErrorProveedorLlm $error) {
                    $ultimoError = $error;
                    Log::warning('asistente.proveedor_fallo', ['proveedor' => $proveedor->nombre, 'modelo' => $proveedor->modelo, 'estado' => $error->estado, 'intento' => $intento, 'error' => mb_substr($error->getMessage(), 0, 300)]);
                    if ($emitio) throw $error;

                    // Límite por minuto con espera corta: vale más esperar unos segundos que cambiar de proveedor.
                    $espera = $error->reintentarEn ?? 0;
                    if ($intento === 1 && $error->esLimiteDeUso() && $espera > 0 && $espera <= 15) {
                        usleep($espera * 1_000_000);
                        continue;
                    }
                    if ($error->esLimiteDeUso()) Cache::put($this->clavePausa($proveedor), true, max(5, min(120, $espera ?: 20)));
                    break;
                }
            }
        }

        throw $ultimoError ?? new ErrorProveedorLlm('Ningún proveedor de IA disponible.');
    }

    private function clavePausa(ProveedorLlm $proveedor): string
    {
        return 'asistente.pausa.' . $proveedor->nombre . '.' . md5($proveedor->url . $proveedor->modelo);
    }

    private function llamar(ProveedorLlm $proveedor, array $mensajes, array $herramientas, ?callable $alTexto): RespuestaLlm
    {
        $streaming = $alTexto !== null && config('asistente.streaming', true);
        $cuerpo = [
            'model' => $proveedor->modelo,
            'messages' => $mensajes,
            'temperature' => (float) config('asistente.temperatura', 0.1),
            'max_tokens' => (int) config('asistente.max_tokens', 900),
        ];
        if ($proveedor->razonamiento) $cuerpo['reasoning_effort'] = $proveedor->razonamiento;
        if ($herramientas !== []) {
            $cuerpo['tools'] = $herramientas;
            $cuerpo['tool_choice'] = 'auto';
        }
        if ($streaming) $cuerpo['stream'] = true;

        $inicio = microtime(true);
        try {
            $peticion = Http::acceptJson()->timeout((int) config('asistente.timeout', 25))->connectTimeout(5);
            if ($proveedor->token) $peticion = $peticion->withToken($proveedor->token);
            if ($streaming) $peticion = $peticion->withOptions(['stream' => true]);
            $response = $peticion->post($proveedor->url, $cuerpo);
        } catch (ConnectionException $error) {
            throw new ErrorProveedorLlm('Sin conexión con el proveedor: ' . $error->getMessage());
        }

        if (! $response->successful()) {
            $reintentar = $response->header('retry-after');
            throw new ErrorProveedorLlm(
                (string) ($response->json('error.message') ?? 'Error HTTP ' . $response->status()),
                $response->status(),
                is_numeric($reintentar) ? (int) ceil((float) $reintentar) : null,
            );
        }

        $respuesta = $streaming
            ? $this->leerStream($response->toPsrResponse()->getBody(), $alTexto)
            : $this->leerCompleta($response->json('choices.0.message'), $response->json('usage'));

        $respuesta->proveedor = $proveedor->nombre;
        $respuesta->modelo = $proveedor->modelo;
        $respuesta->milisegundos = (int) round((microtime(true) - $inicio) * 1000);

        return $respuesta;
    }

    private function leerCompleta(mixed $mensaje, mixed $uso): RespuestaLlm
    {
        if (! is_array($mensaje)) throw new ErrorProveedorLlm('Respuesta del proveedor sin mensaje.');

        return new RespuestaLlm(
            texto: (string) ($mensaje['content'] ?? ''),
            llamadas: $this->normalizarLlamadas($mensaje['tool_calls'] ?? []),
            tokens: is_array($uso) ? (int) ($uso['total_tokens'] ?? 0) : null,
        );
    }

    /** Lee eventos SSE ("data: {...}") acumulando texto y llamadas a herramientas por índice. */
    private function leerStream(StreamInterface $cuerpo, callable $alTexto): RespuestaLlm
    {
        $texto = '';
        $llamadas = [];
        $tokens = null;
        $pendiente = '';

        while (! $cuerpo->eof()) {
            $pendiente .= $cuerpo->read(2048);
            while (($fin = strpos($pendiente, "\n")) !== false) {
                $linea = trim(substr($pendiente, 0, $fin));
                $pendiente = substr($pendiente, $fin + 1);
                if (! str_starts_with($linea, 'data:')) continue;

                $datos = trim(substr($linea, 5));
                if ($datos === '[DONE]') break 2;
                $evento = json_decode($datos, true);
                if (! is_array($evento)) continue;
                if (isset($evento['error'])) throw new ErrorProveedorLlm((string) ($evento['error']['message'] ?? 'Error en el stream.'));

                $uso = $evento['usage'] ?? $evento['x_groq']['usage'] ?? null;
                if (is_array($uso)) $tokens = (int) ($uso['total_tokens'] ?? 0);

                $delta = $evento['choices'][0]['delta'] ?? [];
                if (isset($delta['content']) && $delta['content'] !== '') {
                    $texto .= $delta['content'];
                    $alTexto($delta['content']);
                }
                foreach ($delta['tool_calls'] ?? [] as $parcial) {
                    $i = (int) ($parcial['index'] ?? 0);
                    $llamadas[$i] ??= ['id' => '', 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];
                    if (! empty($parcial['id'])) $llamadas[$i]['id'] = $parcial['id'];
                    $llamadas[$i]['function']['name'] .= $parcial['function']['name'] ?? '';
                    $llamadas[$i]['function']['arguments'] .= $parcial['function']['arguments'] ?? '';
                }
            }
        }

        ksort($llamadas);

        return new RespuestaLlm($texto, $this->normalizarLlamadas(array_values($llamadas)), $tokens);
    }

    /** @return list<array{id: string, type: string, function: array{name: string, arguments: string}}> */
    private function normalizarLlamadas(mixed $llamadas): array
    {
        if (! is_array($llamadas)) return [];
        $normalizadas = [];
        foreach ($llamadas as $i => $llamada) {
            $nombre = (string) ($llamada['function']['name'] ?? '');
            if ($nombre === '') continue;
            $normalizadas[] = [
                'id' => (string) (($llamada['id'] ?? '') ?: 'llamada_' . $i),
                'type' => 'function',
                'function' => ['name' => $nombre, 'arguments' => (string) (($llamada['function']['arguments'] ?? '') ?: '{}')],
            ];
        }

        return $normalizadas;
    }
}
